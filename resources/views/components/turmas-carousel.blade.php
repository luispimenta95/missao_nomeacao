<section id="turmas-abertas" class="py-16 bg-site text-white">
    <div class="container mx-auto px-6 lg:px-24">
        <h2 id="turmas-abertas" class="text-3xl font-bold text-primary text-center mb-12">Turmas Abertas</h2>

        @if($turmas->count() > 0)
            <!-- Carousel Container -->
            <div class="relative -mx-6 lg:-mx-24 px-6 lg:px-24">
                <!-- Track com scroll suave -->
                <div class="overflow-x-auto overflow-y-hidden scrollbar-hide lg:overflow-hidden snap-x snap-mandatory" id="carouselContainer">
                    <div class="flex gap-6 transition-transform duration-500 ease-out" id="carouselTrack" style="width: max-content;">
                        @foreach($turmas as $turma)
                            <div class="turma-card flex-shrink-0 w-72 sm:w-80 snap-center snap-always">
                                <div class="bg-site rounded-3xl card-shadow hover:shadow-2xl transition-all duration-300 h-full overflow-hidden flex flex-col hover:scale-105 cursor-pointer border border-yellow-600" onclick="event.stopPropagation()">
                                        <!-- Logo Section -->
                                        <div class="h-48 bg-gray-100 flex items-center justify-center border-b border-gray-200">
                                            @if($turma->logo_path)
                                                <img src="{{ asset('storage/' . $turma->logo_path) }}" alt="{{ $turma->nomePublicoExibido() }}" class="h-full w-full object-contain p-4">
                                            @else
                                                <div class="text-gray-400 text-center">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                    </svg>
                                                    <p class="text-sm">Logo</p>
                                                </div>
                                            @endif
                                        </div>

                                        <!-- Content Section -->
                                        <div class="p-6 flex flex-col flex-grow">
                                            <div class="flex flex-wrap gap-2 mb-3">
                                                <span class="inline-block px-2 py-1 rounded text-[11px] font-semibold tracking-wide
                                                    @if($turma->estagio() === 'inscricoes_abertas') bg-green-100 text-green-800
                                                    @elseif($turma->estagio() === 'lista_interesse') bg-blue-100 text-blue-800
                                                    @else bg-yellow-100 text-yellow-800
                                                    @endif">{{ $turma->badgePublico() }}</span>
                                                @if($turma->momentoConcursoPublico())
                                                    <span class="inline-block px-2 py-1 rounded text-[11px] font-semibold tracking-wide bg-yellow-600/15 text-yellow-700">{{ $turma->momentoConcursoPublico() }}</span>
                                                @endif
                                            </div>
                                            <h3 class="text-xl font-bold text-primary mb-2">{{ $turma->nomePublicoExibido() }}</h3>

                                            @if($turma->description)
                                                <p class="text-gray-600 text-sm mb-4 flex-grow line-clamp-3">{{ $turma->description }}</p>
                                            @endif

                                            <!-- Info Footer -->
                                            <div class="pt-4 border-t border-gray-200 space-y-3">
                                                <div class="pt-2">
                                                    @php
                                                        $popupOpcoes = $turma->popupOpcoesNormalizadas();
                                                    @endphp
                                                    @if(count($popupOpcoes) > 0)
                                                        <button type="button"
                                                            data-popup-options='@json($popupOpcoes)'
                                                            onclick="openTurmaPopup(this)"
                                                            class="inline-block px-4 py-2 bg-primary text-white rounded-full text-sm font-semibold hover:bg-opacity-90 transition">
                                                            {{ $turma->textoCtaPublico() }}
                                                        </button>
                                                    @elseif($turma->acao_principal === 'checkout' && $turma->aceitaInscricao())
                                                        <button type="button" onclick="openInscricaoModal({{ $turma->id }}, '{{ addslashes($turma->nomePublicoExibido()) }}')" class="inline-block px-4 py-2 bg-primary text-white rounded-full text-sm font-semibold hover:bg-opacity-90 transition">
                                                            {{ $turma->textoCtaPublico() }}
                                                        </button>
                                                    @elseif($turma->linkCta())
                                                        <a href="{{ $turma->linkCta() }}" target="_blank" rel="noopener" class="inline-block px-4 py-2 bg-primary text-white rounded-full text-sm font-semibold hover:bg-opacity-90 transition">
                                                            {{ $turma->textoCtaPublico() }}
                                                        </a>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Navigation Buttons - Desktop only -->
                @if($turmas->count() > 1)
                    <button id="prevBtn" class="absolute left-2 lg:left-0 top-1/2 -translate-y-1/2 z-20 bg-primary text-white rounded-full p-2 lg:p-3 hover:bg-opacity-90 shadow-lg hidden lg:flex items-center justify-center transition-all duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 lg:h-6 lg:w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>

                    <button id="nextBtn" class="absolute right-2 lg:right-0 top-1/2 -translate-y-1/2 z-20 bg-primary text-white rounded-full p-2 lg:p-3 hover:bg-opacity-90 shadow-lg hidden lg:flex items-center justify-center transition-all duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 lg:h-6 lg:w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                @endif
                
                <!-- Scroll indicator for mobile -->
                <div class="flex justify-center mt-4 gap-2 lg:hidden" id="scrollIndicators"></div>
            </div>
        @else
            <div class="max-w-2xl mx-auto">
                <div class="bg-gray-50 rounded-3xl p-12 text-center card-shadow">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto mb-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v6m0 0v6m0-6h6m0 0h6m0-6V3m0 0h-6m0 0H3m6 6v6m0 0v6" />
                    </svg>
                    <h3 class="text-2xl font-bold text-primary mb-2">Em breve</h3>
                    <p class="text-gray-600 text-lg mb-6">Nenhuma turma aberta no momento. Fique atento para as próximas turmas!</p>
                    <a href="#" class="inline-block px-6 py-3 bg-primary text-white rounded-full font-semibold hover:bg-opacity-90 transition">Notifique-me</a>
                </div>
            </div>
        @endif
    </div>
</section>

<div id="turmaPopup" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 hidden">
    <div class="bg-gray-900 rounded-2xl w-full max-w-md p-6 mx-4 text-white border border-yellow-600">
        <div class="flex items-start justify-between mb-4">
            <h4 class="text-lg font-bold text-primary">Escolha a opção</h4>
            <button type="button" id="closeTurmaPopup" class="text-gray-400 hover:text-white text-2xl leading-none">&times;</button>
        </div>
        <div id="turmaPopupOptions" class="space-y-3"></div>
    </div>
</div>

<script>
    window.openTurmaPopup = function(button) {
        const modal = document.getElementById('turmaPopup');
        const list = document.getElementById('turmaPopupOptions');
        if (!modal || !list) return;
        const options = JSON.parse(button.getAttribute('data-popup-options') || '[]');
        list.innerHTML = '';
        options.forEach(function(option) {
            const link = document.createElement('a');
            link.href = option.url;
            link.target = '_blank';
            link.rel = 'noopener';
            link.className = 'block w-full text-center px-4 py-3 bg-primary text-white rounded-lg font-semibold hover:bg-opacity-90';
            link.textContent = option.label;
            list.appendChild(link);
        });
        modal.classList.remove('hidden');
    };
    document.getElementById('closeTurmaPopup')?.addEventListener('click', function() {
        document.getElementById('turmaPopup')?.classList.add('hidden');
    });
</script>

<style>
    /* Hide scrollbar for mobile while keeping functionality */
    .scrollbar-hide {
        -ms-overflow-style: none;  /* Internet Explorer 10+ */
        scrollbar-width: none;  /* Firefox */
    }
    .scrollbar-hide::-webkit-scrollbar {
        display: none;  /* Safari and Chrome */
    }
    
    /* Smooth scroll behavior */
    #carouselContainer {
        scroll-behavior: smooth;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const track = document.getElementById('carouselTrack');
        const container = document.getElementById('carouselContainer');
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        const cards = document.querySelectorAll('.turma-card');
        const indicatorsContainer = document.getElementById('scrollIndicators');

        if (!track || cards.length === 0) return;

        let currentIndex = 0;
        const isMobile = window.innerWidth < 1024;
        
        // Card dimensions
        function getCardWidth() {
            return window.innerWidth < 640 ? 288 : 320; // w-72 or w-80
        }
        
        const gap = 24;

        // Create scroll indicators for mobile
        function createIndicators() {
            if (window.innerWidth >= 1024 || !indicatorsContainer) return;
            
            indicatorsContainer.innerHTML = '';
            for (let i = 0; i < cards.length; i++) {
                const dot = document.createElement('div');
                dot.className = `w-2 h-2 rounded-full transition-all duration-300 ${i === 0 ? 'bg-primary w-4' : 'bg-gray-400'}`;
                dot.dataset.index = i;
                indicatorsContainer.appendChild(dot);
            }
        }

        function updateIndicators() {
            if (window.innerWidth >= 1024 || !indicatorsContainer) return;
            
            const dots = indicatorsContainer.querySelectorAll('div');
            dots.forEach((dot, index) => {
                if (index === currentIndex) {
                    dot.className = 'w-4 h-2 rounded-full transition-all duration-300 bg-primary';
                } else {
                    dot.className = 'w-2 h-2 rounded-full transition-all duration-300 bg-gray-400';
                }
            });
        }

        function updateCarousel() {
            if (window.innerWidth >= 1024) {
                // Desktop: use transform
                const itemWidth = getCardWidth() + gap;
                const offset = -currentIndex * itemWidth;
                track.style.transform = `translateX(${offset}px)`;
            } else {
                // Mobile: use scroll
                const itemWidth = getCardWidth() + gap;
                const scrollPosition = currentIndex * itemWidth;
                container.scrollLeft = scrollPosition;
            }
            updateIndicators();
        }

        function getMaxIndex() {
            const itemWidth = getCardWidth() + gap;
            const containerWidth = container.offsetWidth;
            const visibleCards = Math.floor(containerWidth / itemWidth);
            return Math.max(0, cards.length - visibleCards);
        }

        // Desktop navigation buttons
        if (prevBtn) {
            prevBtn.addEventListener('click', () => {
                currentIndex = Math.max(0, currentIndex - 1);
                updateCarousel();
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                const maxIndex = getMaxIndex();
                currentIndex = Math.min(maxIndex, currentIndex + 1);
                updateCarousel();
            });
        }

        // Mobile: detect scroll position to update indicators
        if (container) {
            container.addEventListener('scroll', () => {
                if (window.innerWidth < 1024) {
                    const itemWidth = getCardWidth() + gap;
                    const scrollPos = container.scrollLeft;
                    currentIndex = Math.round(scrollPos / itemWidth);
                    updateIndicators();
                }
            });
        }

        // Handle window resize
        window.addEventListener('resize', () => {
            const maxIndex = getMaxIndex();
            currentIndex = Math.min(currentIndex, maxIndex);
            createIndicators();
            updateCarousel();
        });

        // Initialize
        createIndicators();
        updateCarousel();
    });
</script>
