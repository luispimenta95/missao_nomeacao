<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Mentoria — Garanta sua vaga</title>

    <!-- Tailwind Play CDN (rápido para protótipo). Em produção, recomenda-se build com Vite + Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#0b2545', /* azul escuro */
                        accent: '#c49a5d',  /* dourado */
                        blush: '#f7e8ef'    /* tom feminino claro */
                    },
                    fontFamily: {
                        sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif']
                    }
                }
            }
        }
    </script>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&family=Poppins:wght@500;700&display=swap" rel="stylesheet">

    <!-- AOS -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <style>
        /* Small tweaks */
        .gradient-hero { background: linear-gradient(120deg,#f7e8ef 0%, rgba(196,154,93,0.06) 40%, #ffffff 100%); }
        .card-shadow { box-shadow: 0 10px 30px rgba(11,37,69,0.08); }
        .glass { background: rgba(255,255,255,0.6); backdrop-filter: blur(6px); }
    </style>

</head>
<body class="antialiased text-gray-800 font-sans">

<!-- Header -->
@include('components.header')

<main class="mt-20">
    @include('components.hero')

    @include('components.problem')

    @include('components.audience')

    <section class="py-16 bg-white">
        <div class="container mx-auto px-6 lg:px-24">
            @include('components.benefits')
        </div>
    </section>

    {{-- Seção de PDFs gratuitos --}}
    @include('components.free-pdfs')

    <section class="py-16 bg-blush">
        <div class="container mx-auto px-6 lg:px-24">
            @include('components.testimonials')
        </div>
    </section>

    <section class="py-16 bg-white">
        <div class="container mx-auto px-6 lg:px-24">
            @include('components.mentor')
        </div>
    </section>

    @include('components.neuroscience')

    @include('components.urgency')

    @include('components.faq')

    <section class="py-16 bg-gradient-to-b from-white to-gray-50">
        <div class="container mx-auto px-6 lg:px-24">
            @include('components.cta')
        </div>
    </section>
</main>

@include('components.footer')

<!-- WhatsApp flutuante -->
<a href="https://wa.me/5511999999999?text=Ol%C3%A1%20quero%20saber%20mais" target="_blank" rel="noopener" class="fixed bottom-6 right-6 z-50 bg-green-500 text-white p-4 rounded-full shadow-lg hover:scale-105 transition-transform" aria-label="WhatsApp">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24"><path d="M20.52 3.48A11.93 11.93 0 0012 0C5.373 0 .001 5.373 0 12c0 2.115.55 4.165 1.59 5.98L0 24l6.21-1.61A11.94 11.94 0 0012 24c6.627 0 12-5.373 12-12 0-3.2-1.25-6.15-3.48-8.52zM12 21.5c-1.64 0-3.25-.43-4.63-1.24l-.33-.19-3.69.96.98-3.6-.21-.37A9.5 9.5 0 012.5 12 9.5 9.5 0 1112 21.5z"></path><path d="M17.4 14.1c-.3-.15-1.78-.88-2.05-.98-.28-.1-.48-.15-.68.15-.19.28-.74.98-.91 1.18-.17.19-.34.22-.64.07-.3-.15-1.27-.47-2.42-1.48-.9-.8-1.5-1.79-1.67-2.09-.17-.3-.02-.46.13-.61.14-.14.3-.35.45-.52.15-.17.2-.28.3-.47.1-.19.05-.36-.02-.51-.07-.15-.68-1.64-.93-2.25-.24-.59-.49-.51-.68-.52l-.58-.01c-.2 0-.52.07-.8.36-.28.29-1.07 1.05-1.07 2.56 0 1.51 1.1 2.97 1.25 3.18.15.21 2.16 3.44 5.24 4.82 3.08 1.38 3.08.92 3.64.86.56-.06 1.8-.73 2.05-1.44.25-.71.25-1.32.17-1.44-.08-.12-.29-.19-.59-.34z"></path></svg>
</a>

<!-- AOS and small scripts -->
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    AOS.init({ once: true, duration: 800 });

    // Simple testimonials carousel
    (function(){
        const slides = document.querySelectorAll('[data-slice]');
        if(!slides.length) return;
        let idx = 0;
        setInterval(()=>{
            slides[idx].classList.add('hidden');
            idx = (idx+1) % slides.length;
            slides[idx].classList.remove('hidden');
        }, 5000);
    })();
</script>

</body>
</html>
