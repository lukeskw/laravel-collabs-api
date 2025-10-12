@component('mail::message')
O processamento da importação de colaboradores falhou.

@if($fileName)
- Arquivo: {{ $fileName }}
@endif

@if($errorMessage)
**Detalhes do erro:** {{ $errorMessage }}
@endif

Por favor, verifique o arquivo e tente novamente.
@endcomponent
