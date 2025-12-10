<section id="materiais" class="py-16 bg-white">
    <div class="container mx-auto px-6 lg:px-24">
        <div class="text-center mb-8" data-aos="fade-up">
            <h2 id="materiais" class="text-2xl font-bold text-primary">Materiais gratuitos em PDF</h2>
            <p class="mt-2 text-gray-600 max-w-2xl mx-auto">Baixe apostilas e roteiros práticos. Antes do download, pedimos alguns dados rápidos para envio e acompanhamento.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                @if(isset($materials) && $materials->count())
                    @foreach($materials as $material)
                        <article class="p-6 rounded-2xl card-shadow hover:scale-105 transition transform bg-white" data-id="{{ $material->id }}" data-aos="zoom-in">
                            <div class="text-accent">
                                <!-- book svg -->
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <path d="M4 19.5A2.5 2.5 0 016.5 17H20" />
                                    <path d="M4 4.5A2.5 2.5 0 016.5 7H20v12" />
                                </svg>
                            </div>
                            <h3 class="mt-4 font-semibold">{{ $material->title }}</h3>
                            <p class="mt-2 text-sm text-gray-600">{{ $material->description }}</p>
                            <button class="mt-4 inline-block px-4 py-2 bg-primary text-white rounded-lg btn-open-pdf">Baixar PDF</button>
                        </article>
                    @endforeach
                @else
                    <div class="col-span-1 sm:col-span-3 p-6 rounded-2xl card-shadow bg-white text-center">
                        <p class="text-gray-600">Nenhum material disponível no momento. Em breve teremos conteúdos gratuitos.</p>
                    </div>
                @endif
            </div>

        <!-- Modal - simple Tailwind modal -->
        <div id="pdfModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 hidden">
            <div class="bg-white rounded-2xl w-full max-w-lg p-6 mx-4">
                <div class="flex items-start justify-between">
                    <h4 class="text-lg font-bold">Para baixar o material, deixe seus dados</h4>
                    <button id="closePdfModal" class="text-gray-500 hover:text-gray-800">✕</button>
                </div>

                <form id="pdfForm" class="mt-4" novalidate>
                    <input type="hidden" name="material_id" id="pdf_file" value="">
                    <input type="hidden" name="utm_source" id="pdf_utm_source" value="site">
                    <input type="hidden" name="utm_medium" id="pdf_utm_medium" value="">
                    <input type="hidden" name="utm_campaign" id="pdf_utm_campaign" value="">

                    <div class="grid grid-cols-1 gap-3">
                        <label class="block">
                            <span class="text-sm text-gray-600">Nome completo</span>
                            <input type="text" name="name" id="pdf_name" class="mt-1 w-full rounded-lg border-gray-200 shadow-sm focus:ring-primary focus:border-primary">
                        </label>

                        <label class="block">
                            <span class="text-sm text-gray-600">Email</span>
                            <input type="email" name="email" id="pdf_email" class="mt-1 w-full rounded-lg border-gray-200 shadow-sm focus:ring-primary focus:border-primary">
                        </label>

                        <label class="block">
                            <span class="text-sm text-gray-600">Telefone celular</span>
                            <input type="text" name="phone" id="pdf_phone" class="mt-1 w-full rounded-lg border-gray-200 shadow-sm focus:ring-primary focus:border-primary">
                        </label>

                        <label class="inline-flex items-center gap-2 mt-2">
                            <input type="checkbox" name="consent" id="pdf_consent" class="rounded border-gray-300 text-primary focus:ring-primary">
                            <span class="text-sm text-gray-600">Concordo com o uso dos meus dados</span>
                        </label>

                        <div id="pdfFormError" class="hidden text-sm text-red-600"></div>
                    </div>

                    <div class="mt-4 flex justify-end gap-3">
                        <button type="button" id="cancelPdf" class="px-4 py-2 rounded-lg border">Cancelar</button>
                        <button type="submit" id="submitPdf" class="px-4 py-2 bg-accent text-white rounded-lg">Baixar agora</button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</section>

<script>
    (function(){
        // Hook up PDF buttons to open modal
        const modal = document.getElementById('pdfModal');
        const pdfFileInput = document.getElementById('pdf_file');

        function getUtmParams(){
            const params = new URLSearchParams(window.location.search);
            return {
                utm_source: params.get('utm_source') || 'site',
                utm_medium: params.get('utm_medium') || '',
                utm_campaign: params.get('utm_campaign') || ''
            }
        }

        document.querySelectorAll('.btn-open-pdf').forEach(btn => {
            btn.addEventListener('click', e => {
                const article = e.target.closest('article');
                const id = article.getAttribute('data-id');
                pdfFileInput.value = id;

                const utm = getUtmParams();
                document.getElementById('pdf_utm_source').value = 'site'; // fixed
                document.getElementById('pdf_utm_medium').value = utm.utm_medium;
                document.getElementById('pdf_utm_campaign').value = utm.utm_campaign;

                modal.classList.remove('hidden');
            });
        });

        document.getElementById('closePdfModal').addEventListener('click', ()=> modal.classList.add('hidden'));
        document.getElementById('cancelPdf').addEventListener('click', ()=> modal.classList.add('hidden'));

        // Phone mask: (xx) xxxxx-xxxx while typing
        const phoneInput = document.getElementById('pdf_phone');
        if(phoneInput){
            let isDeleting = false;
            phoneInput.addEventListener('keydown', function(e){
                isDeleting = (e.key === 'Backspace' || e.key === 'Delete');
            });

            phoneInput.addEventListener('input', function(e){
                // If user is deleting, allow the deletion to proceed without aggressive reformat
                if(isDeleting){
                    isDeleting = false;
                    return;
                }

                let v = e.target.value.replace(/\D/g, '').slice(0,11);
                let formatted = '';
                if(v.length > 0){
                    formatted += '(' + v.slice(0, Math.min(2, v.length)) + ')';
                }
                if(v.length > 2){
                    formatted += ' ';
                    // if 11 digits, mask as 5-4, else 4-4
                    if(v.length > 6){
                        formatted += v.slice(2, 7) + (v.length > 7 ? '-' + v.slice(7) : '');
                    } else {
                        formatted += v.slice(2);
                    }
                }
                e.target.value = formatted;
            });

            // handle paste: format pasted numbers
            phoneInput.addEventListener('paste', function(e){
                const paste = (e.clipboardData || window.clipboardData).getData('text');
                const digits = paste.replace(/\D/g,'').slice(0,11);
                if(digits){
                    e.preventDefault();
                    let formatted = '';
                    if(digits.length > 0) formatted += '(' + digits.slice(0, Math.min(2, digits.length)) + ')';
                    if(digits.length > 2){
                        formatted += ' ';
                        if(digits.length > 6){
                            formatted += digits.slice(2,7) + (digits.length > 7 ? '-' + digits.slice(7) : '');
                        } else {
                            formatted += digits.slice(2);
                        }
                    }
                    e.target.value = formatted;
                }
            });

            // also format on blur to clean trailing chars
            phoneInput.addEventListener('blur', function(e){
                let v = e.target.value.replace(/\D/g, '').slice(0,11);
                if(!v) return;
                if(v.length === 10){
                    e.target.value = '(' + v.slice(0,2) + ') ' + v.slice(2,6) + '-' + v.slice(6);
                } else if(v.length === 11){
                    e.target.value = '(' + v.slice(0,2) + ') ' + v.slice(2,7) + '-' + v.slice(7);
                }
            });
        }

        // Submit form via fetch to /leads with custom PT-BR validation
        document.getElementById('pdfForm').addEventListener('submit', async function(e){
            e.preventDefault();
            const form = e.target;

            // Custom client-side validation (Portuguese messages)
            const name = document.getElementById('pdf_name').value.trim();
            const email = document.getElementById('pdf_email').value.trim();
            const phone = document.getElementById('pdf_phone').value.trim();

            const consentChecked = document.getElementById('pdf_consent').checked;

            if(!name){
                showError('Por favor, informe seu nome.');
                return;
            }
            if(!phone){
                showError('Por favor, informe seu telefone celular.');
                return;
            }

            // Simple email format check
            const emailPattern = /^\S+@\S+\.\S+$/;
            if(!email || !emailPattern.test(email)){
                showError('Por favor, informe um e-mail válido.');
                return;
            }

            if(!consentChecked){
                showError('Você precisa concordar com o uso dos dados.');
                return;
            }

            const data = new FormData(form);
            const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            try{
                const res = await fetch('/leads', { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' }, body: data });
                const json = await res.json();
                if(res.ok && json.success){
                    // redirect to download (server will return proper url)
                    window.location.href = json.redirect_url;
                } else {
                    const msg = (json && json.errors) ? Object.values(json.errors).flat()[0] : 'Erro ao enviar.';
                    showError(msg);
                }
            } catch(err){
                showError('Erro de rede. Tente novamente.');
                console.error(err);
            }
        });

        function showError(message){
            const el = document.getElementById('pdfFormError');
            el.textContent = message;
            el.classList.remove('hidden');
        }
    })();
</script>
