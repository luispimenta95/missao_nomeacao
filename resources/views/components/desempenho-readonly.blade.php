@props(['label', 'valor'])

<div {{ $attributes->merge(['class' => 'mb-6']) }}>
    <p class="text-sm font-semibold text-gray-700">{{ $label }}</p>
    <div class="mt-2">
        @if(filled($valor))
            <x-desempenho-badge :valor="$valor" />
        @else
            <span class="text-sm text-gray-400">Ainda não disponível</span>
        @endif
    </div>
</div>
