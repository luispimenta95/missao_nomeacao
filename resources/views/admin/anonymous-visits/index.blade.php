@extends('layouts.admin')

@section('title', 'Visitas')

@section('content')
    <div>
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-3xl font-bold text-white">Visitas anonimas</h1>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
            <div class="bg-white p-6 rounded shadow">
                <p class="text-gray-600 text-sm">Total de Visitas</p>
                <p class="text-3xl font-bold text-primary">{{ $totalVisits }}</p>
            </div>
            <div class="bg-white p-6 rounded shadow">
                <p class="text-gray-600 text-sm">Sem Saida Registrada</p>
                <p class="text-3xl font-bold text-yellow-600">{{ $activeVisits }}</p>
            </div>
            <div class="bg-white p-6 rounded shadow">
                <p class="text-gray-600 text-sm">Hoje</p>
                <p class="text-3xl font-bold text-green-600">{{ $visitsToday }}</p>
            </div>
        </div>

        <div class="bg-white rounded shadow-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-primary text-white">
                        <tr>
                            <th class="px-6 py-3 text-left text-sm font-semibold">Entrada</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold">Saida</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold">Duracao</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold">IP</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold">Pagina</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold">Origem</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold">IP Info</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($visits as $visit)
                            <tr class="border-t border-gray-200 hover:bg-gray-50 transition align-top">
                                <td class="px-6 py-4 text-sm text-gray-800 whitespace-nowrap">
                                    {{ $visit->entered_at?->format('d/m/Y H:i:s') ?? '-' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600 whitespace-nowrap">
                                    {{ $visit->exited_at?->format('d/m/Y H:i:s') ?? '-' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600 whitespace-nowrap">
                                    @if($visit->duration_seconds !== null)
                                        {{ gmdate('H:i:s', $visit->duration_seconds) }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600 whitespace-nowrap">
                                    {{ $visit->ip_address ?? '-' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600 min-w-72">
                                    <div>
                                        <p class="font-semibold text-gray-800">Entrada</p>
                                        <p class="break-all">{{ $visit->landing_page ?? '-' }}</p>
                                    </div>
                                    <div class="mt-2">
                                        <p class="font-semibold text-gray-800">Saida</p>
                                        <p class="break-all">{{ $visit->exit_page ?? '-' }}</p>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600 min-w-56">
                                    <p class="break-all">{{ $visit->referrer ?? '-' }}</p>
                                    @if($visit->utm_source || $visit->utm_medium || $visit->utm_campaign)
                                        <div class="mt-2 text-xs text-gray-500">
                                            <p>utm_source: {{ $visit->utm_source ?? '-' }}</p>
                                            <p>utm_medium: {{ $visit->utm_medium ?? '-' }}</p>
                                            <p>utm_campaign: {{ $visit->utm_campaign ?? '-' }}</p>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-600 min-w-64">
                                    @if($visit->ip_data)
                                        <dl class="space-y-1">
                                            @foreach($visit->ip_data as $key => $value)
                                                <div>
                                                    <dt class="font-semibold text-gray-800">{{ $key }}</dt>
                                                    <dd class="break-all">{{ $value }}</dd>
                                                </div>
                                            @endforeach
                                        </dl>
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-gray-600">Nenhuma visita registrada</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($visits->hasPages())
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                    {{ $visits->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
