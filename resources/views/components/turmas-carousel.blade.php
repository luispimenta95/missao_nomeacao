<section id="turmas-abertas" class="py-16 bg-white">
    <div class="container mx-auto px-6 lg:px-24">
        <h2 class="text-3xl font-bold text-primary text-center mb-12">Turmas Abertas</h2>

        @if($turmas->count() > 0)
            <!-- Carousel Container -->
            <div class="relative -mx-6 lg:-mx-24 px-6 lg:px-24">
                <!-- Track com scroll suave -->
                <div class="overflow-hidden">
                    <div class="flex gap-6 transition-transform duration-500 ease-out" id="carouselTrack" style="width: max-content;">
                        @foreach($turmas as $turma)
                            <div class="turma-card flex-shrink-0 w-80">
                                <div class="bg-white rounded-3xl card-shadow hover:shadow-2xl transition-all duration-300 h-full overflow-hidden flex flex-col hover:scale-105 cursor-pointer" onclick="event.stopPropagation()">
                                        <!-- Logo Section -->
                                        <div class="h-48 bg-gray-100 flex items-center justify-center border-b border-gray-200">
                                            @if($turma->logo_path)
                                                <img src="{{ asset('storage/' . $turma->logo_path) }}" alt="{{ $turma->title }}" class="h-full w-full object-contain p-4">
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
                                            <h3 class="text-xl font-bold text-primary mb-2">{{ $turma->title }}</h3>

                                            @if($turma->description)
                                                <p class="text-gray-600 text-sm mb-4 flex-grow line-clamp-3">{{ $turma->description }}</p>
                                            @endif

                                            <!-- Info Footer -->
                                            <div class="pt-4 border-t border-gray-200 space-y-3">
                                                @if($turma->start_date)
                                                    <div class="flex items-center text-sm text-gray-600">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                        </svg>
                                                        <span>Início: {{ $turma->start_date->format('d/m/Y') }}</span>
                                                    </div>
                                                @endif

                                                @if($turma->available_slots)
                                                    <div class="flex items-center text-sm text-gray-600">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.856-1.487M15 10a3 3 0 11-6 0 3 3 0 016 0zM6 20h12a3 3 0 003-3v-2a3 3 0 00-3-3H6a3 3 0 00-3 3v2a3 3 0 003 3z" />
                                                        </svg>
                                                        <span>{{ $turma->available_slots }} vagas</span>
                                                    </div>
                                                @endif

                                                <div class="pt-4">
                                                    <button type="button" onclick="openInscricaoModal({{ $turma->id }}, '{{ addslashes($turma->title) }}')" class="inline-block px-4 py-2 bg-primary text-white rounded-full text-sm font-semibold hover:bg-opacity-90 transition">
                                                        Inscrever-se
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Navigation Buttons -->
                @if($turmas->count() > 3)
                    <button id="prevBtn" class="absolute left-0 top-1/2 -translate-y-1/2 z-20 bg-primary text-white rounded-full p-3 hover:bg-opacity-90 shadow-lg hidden lg:flex items-center justify-center transition-all duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>

                    <button id="nextBtn" class="absolute right-0 top-1/2 -translate-y-1/2 z-20 bg-primary text-white rounded-full p-3 hover:bg-opacity-90 shadow-lg hidden lg:flex items-center justify-center transition-all duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                @endif
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

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const track = document.getElementById('carouselTrack');
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        const cards = document.querySelectorAll('.turma-card');

        if (!track || cards.length === 0) return;

        let currentIndex = 0;
        const cardWidth = 320; // card width (w-80)
        const gap = 24; // gap between cards
        const itemWidth = cardWidth + gap;

        function updateCarousel() {
            const offset = -currentIndex * itemWidth;
            track.style.transform = `translateX(${offset}px)`;
        }

        function getMaxIndex() {
            const containerWidth = track.parentElement.offsetWidth;
            const visibleCards = Math.floor(containerWidth / itemWidth);
            return Math.max(0, cards.length - visibleCards);
        }

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

        // Responsive carousel
        window.addEventListener('resize', () => {
            const maxIndex = getMaxIndex();
            currentIndex = Math.min(currentIndex, maxIndex);
            updateCarousel();
        });

        // Inicializa a posição
        updateCarousel();
    });
</script>
