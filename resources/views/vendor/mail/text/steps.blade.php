@foreach ($items as $title => $description)
{{ $loop->iteration }}. {{ $title }}: {{ $description }}
@endforeach
