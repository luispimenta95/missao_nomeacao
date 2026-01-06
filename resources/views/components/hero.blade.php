<section class="py-0 mb-12 bg-site">
    <div class="w-full max-w-6xl mx-auto px-6">
        <div class="grid grid-cols-1 lg:grid-cols-2 items-center gap-8">
            <!-- Left: Text area -->
            <div class="space-y-6">
                <!-- Slot allows custom content to be injected when using the component -->
                @if(isset($slot) && (method_exists($slot, 'isNotEmpty') ? $slot->isNotEmpty() : trim((string) $slot) !== ''))
                    {{ $slot }}
                @else
                    <h1 class="text-white text-3xl sm:text-4xl lg:text-5xl font-extrabold leading-tight max-w-xl">
                        Você não passa em concursos.
                    </h1>
                    <h2 class="text-white text-3xl sm:text-4xl lg:text-5xl font-extrabold leading-tight max-w-xl">
                        Você <span class="text-gold italic font-semibold">se torna</span> alguém que passa.
                    </h2>
                    <p class="text-white/80 text-sm sm:text-base leading-relaxed max-w-xl">
                        Entre na Mentoria Missão Nomeação e viva um processo que vai te fazer sair do piloto automático,
                        assumir o controle da sua preparação e se tornar a pessoa que o Diário Oficial chama pelo nome.
                    </p>
                @endif

                <!-- Saiba mais button -->
                <div>
                    <a href="#saiba-mais" class="inline-block btn-primary uppercase">Quero saber mais</a>
                </div>
            </div>

            <!-- Right: Image -->
            <div>
                <img src="{{ asset('img/site-body/header.jpeg') }}" alt="Hero" class="w-full h-auto object-cover rounded-md">
            </div>
        </div>
    </div>
</section>
