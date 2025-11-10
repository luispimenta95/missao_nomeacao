<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Landing - Downloads</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card-download { cursor: pointer; }
    </style>
</head>
<body class="bg-light">
<div class="container py-5">
    <h1 class="mb-4">Recursos para Download</h1>

    <div class="row g-3">
        <div class="col-md-4">
            <div class="card card-download" data-file-url="pay">
                <img src="https://via.placeholder.com/600x200?text=Arquivo+1" class="card-img-top" alt="Arquivo 1">
                <div class="card-body">
                    <h5 class="card-title">Arquivo Fictício 1</h5>
                    <p class="card-text">Descrição breve do arquivo 1.</p>
                    <button class="btn btn-primary btn-open-form">Comprar</button>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card card-download" data-file-url="pay">
                <img src="https://via.placeholder.com/600x200?text=Arquivo+2" class="card-img-top" alt="Arquivo 2">
                <div class="card-body">
                    <h5 class="card-title">Arquivo Fictício 2</h5>
                    <p class="card-text">Descrição breve do arquivo 2.</p>
                    <button class="btn btn-primary btn-open-form">Comprar</button>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card card-download" data-file-url="pay">
                <img src="https://via.placeholder.com/600x200?text=Arquivo+3" class="card-img-top" alt="Arquivo 3">
                <div class="card-body">
                    <h5 class="card-title">Arquivo Fictício 3</h5>
                    <p class="card-text">Descrição breve do arquivo 3.</p>
                    <button class="btn btn-primary btn-open-form">Comprar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="leadModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Para baixar, preencha seus dados</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <form id="leadForm">
                        <div class="mb-3">
                            <label for="name" class="form-label">Nome completo</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Telefone celular</label>
                            <input type="text" class="form-control" id="phone" name="phone">
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="consent" name="consent" required>
                            <label class="form-check-label" for="consent">Concordo com o uso dos meus dados</label>
                        </div>
                        <input type="hidden" id="utm_source" name="utm_source" value="site">
                        <input type="hidden" id="utm_medium" name="utm_medium">
                        <input type="hidden" id="utm_campaign" name="utm_campaign">
                        <div id="formAlert" class="alert alert-danger d-none" role="alert"></div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" id="submitLead" class="btn btn-primary">Enviar e baixar</button>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    (function(){
        const modalEl = document.getElementById('leadModal');
        const bsModal = new bootstrap.Modal(modalEl);
        let selectedFile = null;

        // Parse UTM params from URL and populate hidden inputs
        function getUtmParams() {
            const params = new URLSearchParams(window.location.search);
            return {
                utm_source: params.get('utm_source') || '',
                utm_medium: params.get('utm_medium') || '',
                utm_campaign: params.get('utm_campaign') || '',
            };
        }

        document.querySelectorAll('.btn-open-form').forEach(btn => {
            btn.addEventListener('click', function(e){
                const card = e.target.closest('.card-download');
                selectedFile = card ? card.dataset.fileUrl : null;
                // set UTM hidden inputs
                // utm_source is fixed as 'site'
                const utm = getUtmParams();
                document.getElementById('utm_source').value = 'site';
                document.getElementById('utm_medium').value = utm.utm_medium;
                document.getElementById('utm_campaign').value = utm.utm_campaign;
                document.getElementById('formAlert').classList.add('d-none');
                bsModal.show();
            });
        });

        document.getElementById('submitLead').addEventListener('click', async function(){
            const form = document.getElementById('leadForm');
            const formData = new FormData(form);

            // Ensure consent is handled properly (checkbox returns 'on')
            if(!document.getElementById('consent').checked){
                showError('Você precisa concordar com o uso dos dados.');
                return;
            }

            // Add CSRF token header
            const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            try{
                const res = await fetch('/leads', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json'
                    },
                    body: formData
                });

                const json = await res.json();
                if(res.ok && json.success){
                    // close modal and redirect to download URL (with utm_source=site already appended by server)
                    bsModal.hide();
                    window.location.href = json.redirect_url;
                } else {
                    let msg = 'Erro ao enviar. Verifique os dados.';
                    if(json && json.errors){
                        const first = Object.values(json.errors)[0];
                        msg = Array.isArray(first) ? first[0] : first;
                    }
                    showError(msg);
                }
            } catch(err){
                showError('Erro de rede. Tente novamente.');
                console.error(err);
            }
        });

        function showError(message){
            const alert = document.getElementById('formAlert');
            alert.textContent = message;
            alert.classList.remove('d-none');
        }
    })();
</script>

</body>
</html>
