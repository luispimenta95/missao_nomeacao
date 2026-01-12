<section class="py-10 md:py-12 mb-8 md:mb-12 bg-site">
    <div class="w-full max-w-7xl mx-auto px-6">
        <div class="grid grid-cols-1 lg:grid-cols-2 items-center gap-6 sm:gap-8 lg:gap-16">
            <!-- Image first on mobile, right on large screens -->
            <div class="flex items-center justify-center lg:col-start-2">
                <div class="relative w-full max-w-md lg:max-w-lg overflow-hidden">
                    <!-- Decorative background element -->
                    <div class="absolute inset-0 bg-gradient-to-br from-[#1e90ff]/10 to-[#f0c419]/10 rounded-3xl blur-2xl"></div>
                    <!-- Image -->
                    <img src="{{ asset('img/site-body/selfie.png') }}" alt="Hero" class="relative w-full h-auto object-cover rounded-2xl shadow-2xl shadow-[#1e90ff]/20 border-2 border-[#1e90ff]/20">
                </div>
            </div>

            <!-- Text second on mobile, left on large screens -->
            <div class="space-y-6 lg:col-start-1">
                <!-- Slot allows custom content to be injected when using the component -->
                @if(isset($slot) && (method_exists($slot, 'isNotEmpty') ? $slot->isNotEmpty() : trim((string) $slot) !== ''))
                    {{ $slot }}
                @else
                    <h1 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold leading-tight max-w-xl space-y-2">
                        <span class="block text-[#1e90ff]">Você não passa em concursos.</span>
                        <span class="block text-[#f0c419]">Você <span class="text-[#f0c419] italic font-semibold">se torna</span> alguém que passa.</span>
                    </h1>
                    <p class="text-white/80 text-sm sm:text-base leading-relaxed max-w-xl">
                        Entre na Mentoria Missão Nomeação e viva um processo que vai te fazer sair do piloto automático,
                        assumir o controle da sua preparação e se tornar a pessoa que o Diário Oficial chama pelo nome.
                    </p>
                @endif

                <!-- Saiba mais button -->
                <div>
                    <a
                        href="https://wa.me/message/I53LOYY2D7CNI1"
                        class="inline-flex items-center gap-2 text-white uppercase tracking-wider text-base font-bold hover:text-[#f0c419] transition-colors duration-300 border-b-2 border-transparent hover:border-[#f0c419] pb-1"
                    >
                        → Quero saber mais
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
