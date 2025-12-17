@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
    <div>
        <h1 class="text-3xl font-bold text-white mb-8">Dashboard</h1>
        <p class="text-gray-300 mb-8">Bem-vindo ao painel administrativo da Missão Nomeação!</p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Turmas Card -->
                <a href="{{ route('turmas.index') }}" class="block bg-gray-900 overflow-hidden shadow-sm sm:rounded-lg hover:shadow-lg transition border border-yellow-600">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-yellow-600 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-400 truncate">Turmas</dt>
                                    <dd class="text-lg font-medium text-white">{{ \App\Models\Turma::count() }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </a>

                <!-- Materiais Card -->
                <a href="{{ route('materiais.index') }}" class="block bg-gray-900 overflow-hidden shadow-sm sm:rounded-lg hover:shadow-lg transition border border-yellow-600">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-yellow-600 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-400 truncate">Materiais</dt>
                                    <dd class="text-lg font-medium text-white">{{ \App\Models\Material::count() }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </a>

                <!-- Inscrições Card -->
                <a href="{{ route('inscricoes.index') }}" class="block bg-gray-900 overflow-hidden shadow-sm sm:rounded-lg hover:shadow-lg transition border border-yellow-600">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-yellow-600 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-400 truncate">Inscrições</dt>
                                    <dd class="text-lg font-medium text-white">{{ \App\Models\Inscricao::count() }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </a>

                <!-- Leads Card -->
                <a href="{{ route('leads.index') }}" class="block bg-gray-900 overflow-hidden shadow-sm sm:rounded-lg hover:shadow-lg transition border border-yellow-600">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-yellow-600 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-400 truncate">Leads</dt>
                                    <dd class="text-lg font-medium text-white">{{ \App\Models\Lead::count() }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </div>
@endsection
