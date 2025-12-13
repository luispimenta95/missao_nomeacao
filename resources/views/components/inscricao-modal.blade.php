<!-- Modal de Inscrição em Turma -->
<div id="inscricaoModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 hidden">
    <div class="bg-white rounded-2xl w-full max-w-lg p-6 mx-4">
        <div class="flex items-start justify-between">
            <h4 class="text-lg font-bold text-primary">Inscrever-se na turma</h4>
            <button id="closeInscricaoModal" class="text-gray-500 hover:text-gray-800 text-2xl leading-none">&times;</button>
        </div>

        <p class="mt-2 text-sm text-gray-600" id="inscricaoTurmaTitle"></p>

        <form id="inscricaoForm" class="mt-4" novalidate>
            <input type="hidden" name="turma_id" id="inscricao_turma_id" value="">

            <div class="grid grid-cols-1 gap-3">
                <label class="block">
                    <span class="text-sm text-gray-600">Nome completo *</span>
                    <input type="text" name="name" id="inscricao_name" required class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:ring-primary focus:border-primary">
                </label>

                <label class="block">
                    <span class="text-sm text-gray-600">Email *</span>
                    <input type="email" name="email" id="inscricao_email" required class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:ring-primary focus:border-primary">
                </label>

                <label class="block">
                    <span class="text-sm text-gray-600">Telefone celular *</span>
                    <input type="text" name="phone" id="inscricao_phone" required class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:ring-primary focus:border-primary">
                </label>

                <div id="inscricaoFormError" class="hidden text-sm text-red-600 bg-red-50 p-3 rounded-lg"></div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button type="button" id="cancelInscricao" class="px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-50">Cancelar</button>
                <button type="submit" id="submitInscricao" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-opacity-90 font-semibold">Continuar</button>
            </div>
        </form>
    </div>
</div>

<script>
    (function(){
        const modal = document.getElementById('inscricaoModal');
        const turmaIdInput = document.getElementById('inscricao_turma_id');
        const turmaTitleEl = document.getElementById('inscricaoTurmaTitle');
        const form = document.getElementById('inscricaoForm');
        const errorDiv = document.getElementById('inscricaoFormError');

        // Phone mask: (xx) xxxxx-xxxx
        const phoneInput = document.getElementById('inscricao_phone');
        if(phoneInput){
            phoneInput.addEventListener('input', function(e){
                let val = e.target.value.replace(/\D/g, '');
                if(val.length > 11) val = val.substring(0,11);
                
                if(val.length <= 10){
                    val = val.replace(/^(\d{2})(\d{0,4})(\d{0,4}).*/, function(_, ddd, p1, p2){
                        let res = ddd ? '('+ddd+')' : '';
                        if(p1) res += ' ' + p1;
                        if(p2) res += '-' + p2;
                        return res;
                    });
                } else {
                    val = val.replace(/^(\d{2})(\d{5})(\d{0,4}).*/, function(_, ddd, p1, p2){
                        return '('+ddd+') '+p1 + (p2 ? '-'+p2 : '');
                    });
                }
                e.target.value = val;
            });
        }

        // Open modal function
        window.openInscricaoModal = function(turmaId, turmaTitle){
            turmaIdInput.value = turmaId;
            turmaTitleEl.textContent = turmaTitle;
            errorDiv.classList.add('hidden');
            form.reset();
            turmaIdInput.value = turmaId; // reset clears hidden fields
            modal.classList.remove('hidden');
        };

        // Close modal
        function closeModal(){
            modal.classList.add('hidden');
            form.reset();
            errorDiv.classList.add('hidden');
        }

        document.getElementById('closeInscricaoModal').addEventListener('click', closeModal);
        document.getElementById('cancelInscricao').addEventListener('click', closeModal);

        // Submit form
        form.addEventListener('submit', async function(e){
            e.preventDefault();
            errorDiv.classList.add('hidden');

            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());

            // Client-side validation
            if(!data.name || !data.email || !data.phone || !data.turma_id){
                errorDiv.textContent = 'Por favor, preencha todos os campos obrigatórios.';
                errorDiv.classList.remove('hidden');
                return;
            }

            const submitBtn = document.getElementById('submitInscricao');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Processando...';

            try {
                const response = await fetch('/inscricoes', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if(response.ok && result.success){
                    // Close modal and redirect to checkout
                    closeModal();
                    if(result.redirect_url){
                        window.open(result.redirect_url, '_blank');
                    }
                } else {
                    errorDiv.textContent = result.message || 'Erro ao processar inscrição. Tente novamente.';
                    errorDiv.classList.remove('hidden');
                }
            } catch(error){
                errorDiv.textContent = 'Erro de conexão. Tente novamente.';
                errorDiv.classList.remove('hidden');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Continuar';
            }
        });
    })();
</script>
