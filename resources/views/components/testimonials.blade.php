<div id="depoimentos" class="text-center">
    <h2 class="text-3xl font-bold text-primary mb-2" data-aos="fade-up">Depoimentos</h2>
    <p class="text-gray-600 max-w-2xl mx-auto mb-12" data-aos="fade-up" data-aos-delay="100">Histórias reais de quem transformou resultados com o programa.</p>

    <!-- Carrossel de Slides -->
    <div class="relative w-full max-w-3xl mx-auto" data-aos="fade-up">
        <div class="relative bg-black rounded-2xl overflow-hidden card-shadow" style="aspect-ratio: 3/4;">
            <!-- Container de Slides -->
            <div class="relative w-full h-full">
                @php
                    $testimonialController = new \App\Http\Controllers\TestimonialController();
                    $testimonials = $testimonialController->getImages();
                @endphp

                @forelse($testimonials as $index => $testimonial)
                    <div class="testimonial-slide absolute inset-0 transition-opacity duration-500 flex items-center justify-center bg-black {{ $index === 0 ? 'opacity-100 visible' : 'opacity-0 invisible' }}" data-slide="{{ $index }}">
                        <img 
                            src="{{ asset($testimonial['image']) }}" 
                            alt="Depoimento {{ $index + 1 }}" 
                            class="max-w-full max-h-full object-contain"
                            loading="lazy"
                        >
                    </div>
                @empty
                    <div class="w-full h-full flex items-center justify-center text-gray-400">
                        <p>Nenhuma imagem de depoimento encontrada</p>
                    </div>
                @endforelse
            </div>

            <!-- Controles de Navegação -->
            @if(count($testimonials) > 1)
                <!-- Botão Anterior -->
                <button 
                    class="absolute left-4 top-1/2 -translate-y-1/2 z-10 bg-primary hover:bg-primary-light text-black p-3 rounded-full transition-all shadow-lg"
                    aria-label="Slide anterior"
                    onclick="testimonialCarousel.prev()"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </button>

                <!-- Botão Próximo -->
                <button 
                    class="absolute right-4 top-1/2 -translate-y-1/2 z-10 bg-primary hover:bg-primary-light text-black p-3 rounded-full transition-all shadow-lg"
                    aria-label="Próximo slide"
                    onclick="testimonialCarousel.next()"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </button>


            @endif
        </div>
    </div>
</div>

<script>
    const testimonialCarousel = (() => {
        let currentSlide = 0;
        const slides = document.querySelectorAll('.testimonial-slide');
        const totalSlides = slides.length;
        let autoPlayInterval;

        const showSlide = (index) => {
            if (totalSlides === 0) return;
            
            currentSlide = (index + totalSlides) % totalSlides;
            
            slides.forEach((slide, i) => {
                if (i === currentSlide) {
                    slide.classList.add('opacity-100', 'visible');
                    slide.classList.remove('opacity-0', 'invisible');
                } else {
                    slide.classList.add('opacity-0', 'invisible');
                    slide.classList.remove('opacity-100', 'visible');
                }
            });
        };

        const startAutoPlay = () => {
            if (totalSlides > 1) {
                autoPlayInterval = setInterval(() => {
                    showSlide(currentSlide + 1);
                }, 5000);
            }
        };

        const stopAutoPlay = () => {
            clearInterval(autoPlayInterval);
        };

        const resetAutoPlay = () => {
            stopAutoPlay();
            startAutoPlay();
        };

        return {
            next: () => {
                showSlide(currentSlide + 1);
                resetAutoPlay();
            },
            prev: () => {
                showSlide(currentSlide - 1);
                resetAutoPlay();
            },
            goTo: (index) => {
                showSlide(index);
                resetAutoPlay();
            },
            init: () => {
                showSlide(0);
                startAutoPlay();
            }
        };
    })();

    // Inicializa o carrossel quando o DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => testimonialCarousel.init());
    } else {
        testimonialCarousel.init();
    }
</script>
