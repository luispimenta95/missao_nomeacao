@props(['valor'])

@if(filled($valor))
<span {{ $attributes->merge(['class' => 'inline-block px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-800 whitespace-nowrap']) }}>{{ $valor }}</span>
@else
<span class="text-gray-400">—</span>
@endif
