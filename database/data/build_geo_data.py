#!/usr/bin/env python3
"""Regenera database/data/cities.csv e neighborhoods.csv.

Fontes (IBGE, gratuitas):
  - API de localidades: lista dos 5.570 municípios.
  - Malha de Bairros do Censo 2022 (geoftp.ibge.gov.br): nomes dos bairros delimitados pelas prefeituras.
Só os nomes são guardados — os polígonos não são necessários.

Uso: python3 database/data/build_geo_data.py
"""
import csv, glob, io, json, os, struct, tempfile, urllib.request, zipfile

HERE = os.path.dirname(os.path.abspath(__file__))
BAIRROS = "https://geoftp.ibge.gov.br/organizacao_do_territorio/malhas_territoriais/malhas_de_setores_censitarios__divisoes_intramunicipais/censo_2022/bairros/shp/UF/{uf}_bairros_CD2022.zip"
MUNICIPIOS = "https://servicodados.ibge.gov.br/api/v1/localidades/municipios?orderBy=nome"


def fetch(url):
    with urllib.request.urlopen(url, timeout=180) as r:
        return r.read()


def read_dbf(data):
    n, hl, rl = struct.unpack("<IHH", data[4:12])
    fields, p = [], 32
    while data[p] != 0x0D:
        fields.append((data[p:p + 11].split(b"\0")[0].decode(), data[p + 16]))
        p += 32
    for i in range(n):
        rec, q, row = data[hl + i * rl: hl + (i + 1) * rl], 1, {}
        for name, length in fields:
            row[name] = rec[q:q + length].decode("utf-8", "ignore").strip()
            q += length
        yield row


cities = {}
for m in json.loads(fetch(MUNICIPIOS)):
    sigla = m["regiao-imediata"]["regiao-intermediaria"]["UF"]["sigla"]
    cities[str(m["id"])] = (sigla, m["nome"])

ufs = sorted({s for s, _ in cities.values()})
bairros = {}
for uf in ufs:
    try:
        z = zipfile.ZipFile(io.BytesIO(fetch(BAIRROS.format(uf=uf))))
    except urllib.error.HTTPError:
        print(uf, "sem arquivo de bairros no IBGE")
        continue
    dbf = next(n for n in z.namelist() if n.lower().endswith(".dbf"))
    for row in read_dbf(z.read(dbf)):
        name = " ".join(row["NM_BAIRRO"].split())
        if name:
            bairros.setdefault(row["CD_MUN"], {}).setdefault(name.casefold(), name)
    print(uf, "ok")

with open(os.path.join(HERE, "cities.csv"), "w", newline="", encoding="utf-8") as f:
    w = csv.writer(f, delimiter=";")
    for code in sorted(cities):
        w.writerow([code, cities[code][0], cities[code][1]])

with open(os.path.join(HERE, "neighborhoods.csv"), "w", newline="", encoding="utf-8") as f:
    w = csv.writer(f, delimiter=";")
    for code in sorted(bairros):
        for name in sorted(bairros[code].values(), key=str.casefold):
            w.writerow([code, name])

print(len(cities), "municípios;", sum(len(v) for v in bairros.values()), "bairros em", len(bairros), "municípios")
