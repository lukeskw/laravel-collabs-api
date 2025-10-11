@component('mail::message')
{{ $messageText }}

- Total processados: {{ $result->total() }}
- Criados: {{ $result->created }}
- Atualizados: {{ $result->updated }}
- Ignorados: {{ $result->skipped }}

@endcomponent
