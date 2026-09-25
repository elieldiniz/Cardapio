@props(['model' => 'photoUpload'])
{{--
    Lets the dono pick several photos at once but uploads them one at a time
    into a single-file property: Livewire's S3 temporary upload driver (used on
    Laravel Cloud) rejects multi-file uploads.
--}}
<input
    type="file"
    accept="image/*"
    multiple
    class="sr-only"
    x-data="{
        async send(files) {
            for (const file of files) {
                await new Promise((done) => $wire.upload('{{ $model }}', file, done, done));
            }
        },
    }"
    x-on:change="send([...$event.target.files]); $event.target.value = ''"
    {{ $attributes }}
>
