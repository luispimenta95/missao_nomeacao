<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') - Missão Nomeação</title>
    @include('components.analytics')
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#000000',
                        'primary-light': '#BF8F00',
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-site">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <div id="admin-sidebar" class="fixed inset-y-0 left-0 z-40 flex w-64 shrink-0 -translate-x-full flex-col bg-primary shadow-lg transition-transform md:static md:translate-x-0">
            <div class="border-b border-primary-light p-6">
                <h1 class="text-2xl font-bold text-white">Missão<br>Nomeação</h1>
            </div>

            <nav class="mt-4 flex-1 overflow-y-auto pb-6">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center px-6 py-3 text-white hover:bg-primary-light transition @if(request()->routeIs('admin.dashboard')) bg-primary-light @endif">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-3m0 0l7-4 7 4M5 9v10a1 1 0 001 1h12a1 1 0 001-1V9m-9 11l4-4m0 0l4 4m-4-4V3" />
                    </svg>
                    Dashboard
                </a>

                <a href="{{ route('turmas.index') }}" class="flex items-center px-6 py-3 text-white hover:bg-primary-light transition @if(request()->routeIs('turmas.*')) bg-primary-light @endif">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                    Turmas
                </a>

                <a href="{{ route('alunos.index') }}" class="flex items-center px-6 py-3 text-white hover:bg-primary-light transition @if(request()->routeIs('alunos.*')) bg-primary-light @endif">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    Alunos
                </a>

                <a href="{{ route('desempenho.index') }}" class="flex items-center px-6 py-3 text-white hover:bg-primary-light transition @if(request()->routeIs('desempenho.*')) bg-primary-light @endif">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    Desempenho
                </a>

                <a href="{{ route('relatorios-pdf.index') }}" class="flex items-center px-6 py-3 text-white hover:bg-primary-light transition @if(request()->routeIs('relatorios-pdf.*')) bg-primary-light @endif">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                    PDF do relatório
                </a>

                <a href="{{ route('relatorios-pdf-contingencia.index') }}" class="flex items-center px-6 py-3 text-white hover:bg-primary-light transition @if(request()->routeIs('relatorios-pdf-contingencia.*')) bg-primary-light @endif">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Contingência de relatórios
                </a>

                <a href="{{ route('materiais.index') }}" class="flex items-center px-6 py-3 text-white hover:bg-primary-light transition @if(request()->routeIs('materiais.*')) bg-primary-light @endif">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Materiais
                </a>

                <a href="{{ route('inscricoes.index') }}" class="flex items-center px-6 py-3 text-white hover:bg-primary-light transition @if(request()->routeIs('inscricoes.*')) bg-primary-light @endif">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.856-1.487M15 10a3 3 0 11-6 0 3 3 0 016 0zM6 20h12a3 3 0 003-3v-2a3 3 0 00-3-3H6a3 3 0 00-3 3v2a3 3 0 003 3z" />
                    </svg>
                    Inscrições
                </a>

                <a href="{{ route('leads.index') }}" class="flex items-center px-6 py-3 text-white hover:bg-primary-light transition @if(request()->routeIs('leads.*')) bg-primary-light @endif">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    Leads
                </a>

                <a href="{{ route('anonymous-visits.index') }}" class="flex items-center px-6 py-3 text-white hover:bg-primary-light transition @if(request()->routeIs('anonymous-visits.*')) bg-primary-light @endif">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    Visitas
                </a>

                <div class="border-t border-primary-light mt-8 pt-8">
                    <div class="px-6 py-3 text-white text-sm">
                        <p class="text-gray-300">Conectado como:</p>
                        <p class="font-semibold">{{ Auth::user()->name }}</p>
                    </div>
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="flex items-center px-6 py-3 text-white hover:bg-primary-light transition w-full text-left">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            Sair
                        </button>
                    </form>
                </div>
            </nav>
        </div>

        <button type="button" id="admin-sidebar-backdrop" class="fixed inset-0 z-30 hidden bg-black/40 md:hidden" aria-label="Fechar menu"></button>

        <!-- Main Content -->
        <div class="flex min-w-0 flex-1 flex-col overflow-auto bg-site">
            <div class="flex items-center gap-3 bg-primary px-4 py-3 text-white md:hidden">
                <button type="button" id="admin-menu" class="rounded-lg p-2 hover:bg-white/10" aria-label="Abrir menu">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <span class="font-semibold">Missão Nomeação</span>
            </div>
            @hasSection('bare')
                @yield('bare')
            @else
            <div class="p-4 md:p-8">
                @yield('content')
            </div>
            @endif
        </div>
    </div>
    <script>
        (function () {
            const sidebar = document.getElementById('admin-sidebar');
            const backdrop = document.getElementById('admin-sidebar-backdrop');
            const menu = document.getElementById('admin-menu');
            if (!sidebar || !backdrop || !menu) {
                return;
            }

            function abrir(aberto) {
                sidebar.classList.toggle('-translate-x-full', !aberto);
                backdrop.classList.toggle('hidden', !aberto);
            }

            menu.addEventListener('click', function () {
                abrir(sidebar.classList.contains('-translate-x-full'));
            });
            backdrop.addEventListener('click', function () {
                abrir(false);
            });
            sidebar.querySelectorAll('a').forEach(function (link) {
                link.addEventListener('click', function () {
                    if (window.matchMedia('(max-width: 767px)').matches) {
                        abrir(false);
                    }
                });
            });
        })();
    </script>
</body>

</html>