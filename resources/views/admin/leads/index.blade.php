@extends('layouts.admin')

@section('title', 'Leads')

@section('content')
    <div>
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Leads</h1>
            <a href="{{ route('leads.export') }}" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded transition">↓ Exportar CSV</a>
        </div>

        @if(session('success'))
            <div class="p-4 bg-green-100 text-green-800 rounded mb-4">{{ session('success') }}</div>
        @endif

        <div class="bg-white rounded shadow-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-primary text-white">
                        <tr>
                            <th class="px-6 py-3 text-left text-sm font-semibold">Nome</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold">Email</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold">Telefone</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold">Material</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold">Consentimento</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold">Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($leads as $lead)
                            <tr class="border-t border-gray-200 hover:bg-gray-50 transition">
                                <td class="px-6 py-4 text-sm text-gray-800">{{ $lead->name }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    <a href="mailto:{{ $lead->email }}" class="text-primary hover:underline">{{ $lead->email }}</a>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">{{ $lead->phone ?? '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    @if($lead->material)
                                        {{ $lead->material->title }}
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    @if($lead->consent)
                                        <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs font-semibold">Sim</span>
                                    @else
                                        <span class="px-2 py-1 bg-red-100 text-red-800 rounded text-xs font-semibold">Não</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">{{ $lead->created_at->format('d/m/Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-gray-600">Nenhum lead cadastrado</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($leads->hasPages())
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                    {{ $leads->links() }}
                </div>
            @endif
        </div>

        <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white p-6 rounded shadow">
                <p class="text-gray-600 text-sm">Total de Leads</p>
                <p class="text-3xl font-bold text-primary">{{ $totalLeads }}</p>
            </div>
            <div class="bg-white p-6 rounded shadow">
                <p class="text-gray-600 text-sm">Com Consentimento</p>
                <p class="text-3xl font-bold text-green-600">{{ $leadsWithConsent }}</p>
            </div>
            <div class="bg-white p-6 rounded shadow">
                <p class="text-gray-600 text-sm">Este Mês</p>
                <p class="text-3xl font-bold text-red-600">{{ $leadsThisMonth }}</p>
            </div>
        </div>
    </div>
@endsection
