{{-- Living documentation of the dono panel's shared UI components (dev/testing only). --}}
<!DOCTYPE html>
<html lang="pt-BR" style="--accent: {{ request('accent', \App\Models\Restaurant::DEFAULT_ACCENT_COLOR) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Componentes · Painel</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen">
    <main class="mx-auto flex max-w-[980px] flex-col gap-8 px-4 py-8 sm:px-8">
        <x-ui.page-header title="Componentes do painel" subtitle="Biblioteca compartilhada usada por todas as telas do painel do dono. Use ?accent=%232E9E6B para testar outra cor de destaque." />

        <section data-component="button" class="flex flex-col gap-3">
            <h2 class="text-[13px] font-bold tracking-wide text-ink/50 uppercase">Botão — &lt;x-ui.button&gt;</h2>
            <x-ui.card>
                <div class="flex flex-wrap items-center gap-2.5">
                    <x-ui.button>Primário</x-ui.button>
                    <x-ui.button variant="secondary">Secundário</x-ui.button>
                    <x-ui.button variant="danger">Excluir</x-ui.button>
                    <x-ui.button variant="ghost">Fantasma</x-ui.button>
                    <x-ui.button size="sm">Pequeno</x-ui.button>
                    <x-ui.button disabled>Desabilitado</x-ui.button>
                    <x-ui.button href="#" variant="secondary">Como link</x-ui.button>
                </div>
            </x-ui.card>
        </section>

        <section data-component="card" class="flex flex-col gap-3">
            <h2 class="text-[13px] font-bold tracking-wide text-ink/50 uppercase">Card — &lt;x-ui.card&gt; / &lt;x-ui.stat-card&gt;</h2>
            <div class="flex flex-wrap gap-3.5">
                <x-ui.stat-card label="Pratos ativos" value="12" />
                <x-ui.stat-card label="Esgotados" value="2" />
                <x-ui.stat-card label="Visualizações hoje" value="482" />
            </div>
            <x-ui.card title="Ações rápidas">
                <p class="text-[13.5px] text-ink/60">Conteúdo do card com título.</p>
            </x-ui.card>
        </section>

        <section data-component="form-field" class="flex flex-col gap-3">
            <h2 class="text-[13px] font-bold tracking-wide text-ink/50 uppercase">Campo de formulário — &lt;x-ui.field&gt; + input/select/textarea</h2>
            <x-ui.card>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="Nome" hint="Como aparece no cardápio.">
                        <x-ui.input placeholder="Burger Clássico" />
                    </x-ui.field>
                    <x-ui.field label="Categoria">
                        <x-ui.select>
                            <option>Burgers</option>
                            <option>Fumados</option>
                        </x-ui.select>
                    </x-ui.field>
                    <x-ui.field label="Preço">
                        <x-ui.input placeholder="R$ 0,00" inputmode="decimal" />
                    </x-ui.field>
                    <x-ui.field label="Descrição" class="sm:col-span-2">
                        <x-ui.textarea placeholder="Descrição completa do prato" />
                    </x-ui.field>
                </div>
            </x-ui.card>
        </section>

        <section data-component="modal" class="flex flex-col gap-3" x-data>
            <h2 class="text-[13px] font-bold tracking-wide text-ink/50 uppercase">Modal — &lt;x-ui.modal&gt;</h2>
            <x-ui.card>
                <x-ui.button x-on:click="$dispatch('open-modal', 'preview')">Abrir modal</x-ui.button>
            </x-ui.card>
            <x-ui.modal name="preview" title="Novo prato">
                <x-ui.field label="Nome"><x-ui.input /></x-ui.field>
                <x-slot:footer>
                    <x-ui.button class="flex-1" x-on:click="$dispatch('close-modal', 'preview')">Salvar</x-ui.button>
                    <x-ui.button variant="secondary" class="flex-1" x-on:click="$dispatch('close-modal', 'preview')">Cancelar</x-ui.button>
                </x-slot:footer>
            </x-ui.modal>
        </section>

        <section data-component="toast" class="flex flex-col gap-3" x-data>
            <h2 class="text-[13px] font-bold tracking-wide text-ink/50 uppercase">Toast — &lt;x-ui.toast&gt;</h2>
            <x-ui.card>
                <div class="flex gap-2.5">
                    <x-ui.button x-on:click="$dispatch('toast', { message: 'Prato salvo!', type: 'success' })">Toast de sucesso</x-ui.button>
                    <x-ui.button variant="secondary" x-on:click="$dispatch('toast', { message: 'Não foi possível salvar.', type: 'error' })">Toast de erro</x-ui.button>
                </div>
            </x-ui.card>
        </section>

        <section data-component="states" class="flex flex-col gap-3">
            <h2 class="text-[13px] font-bold tracking-wide text-ink/50 uppercase">Estados — &lt;x-ui.empty-state&gt; / &lt;x-ui.skeleton&gt; / &lt;x-ui.error-state&gt;</h2>
            <x-ui.card>
                <x-ui.empty-state title="Nenhum prato ainda" description="Cadastre o primeiro prato para ele aparecer no seu cardápio em vídeo.">
                    <x-ui.button>+ Novo prato</x-ui.button>
                </x-ui.empty-state>
            </x-ui.card>
            <x-ui.card>
                <x-ui.skeleton rows="3" avatar />
            </x-ui.card>
            <x-ui.error-state>
                <x-ui.button size="sm" variant="secondary">Tentar de novo</x-ui.button>
            </x-ui.error-state>
        </section>
    </main>

    <x-ui.toast />
    @livewireScripts
</body>
</html>
