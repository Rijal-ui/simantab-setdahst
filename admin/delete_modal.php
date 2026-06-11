<!-- Bootstrap 5 Confirmation Modal -->
<div class="modal fade" id="app-bootstrap-modal" tabindex="-1" aria-labelledby="app-bootstrap-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-bottom-0">
                <h5 class="modal-title fw-bold" id="app-bootstrap-modal-label">Konfirmasi</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-danger-subtle d-flex align-items-center justify-content-center text-danger me-3" style="width: 50px; height: 50px; flex-shrink: 0;">
                        <i class="bi bi-exclamation-triangle fs-4"></i>
                    </div>
                    <p id="app-bootstrap-modal-text" class="mb-0 text-secondary fw-medium"></p>
                </div>
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-light px-4 fw-bold" data-bs-dismiss="modal">Batal</button>
                <button type="button" id="app-bootstrap-modal-confirm" class="btn btn-danger px-4 fw-bold shadow-sm">Ya, Lanjutkan</button>
            </div>
        </div>
    </div>
</div>

<style>
    .bg-danger-subtle { background-color: #fee2e2; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modalEl = document.getElementById('app-bootstrap-modal');
    const bsModal = new bootstrap.Modal(modalEl);
    const modalText = document.getElementById('app-bootstrap-modal-text');
    const btnConfirm = document.getElementById('app-bootstrap-modal-confirm');
    let currentAction = null;

    function openModal(message, action) {
        modalText.innerHTML = message;
        currentAction = action;
        bsModal.show();
    }

    // Handle Delete Triggers
    document.querySelectorAll('.delete-trigger').forEach(el => {
        el.addEventListener('click', function(e) {
            e.preventDefault();
            const url = this.getAttribute('href');
            const message = this.getAttribute('data-message') || 'Apakah Anda yakin ingin menghapus data ini?';
            openModal(message, { type: 'navigate', url });
        });
    });

    // Handle Payment Confirmation Triggers
    document.querySelectorAll('.btn-confirm').forEach(el => {
        el.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            openModal('Apakah Anda yakin ingin mengonfirmasi pembayaran untuk invoice ini?', { 
                type: 'submit', 
                action: 'confirm_payment.php', 
                method: 'post', 
                fields: { invoice_id: id } 
            });
        });
    });

    // Handle Forms that require confirmation
    document.querySelectorAll('form.requires-confirm').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const message = this.getAttribute('data-message') || 'Lanjutkan tindakan ini?';
            openModal(message, { type: 'submit-form', form: this });
        });
    });

    // Handle Confirm Action
    btnConfirm.addEventListener('click', function() {
        if (!currentAction) {
            bsModal.hide();
            return;
        }

        if (currentAction.type === 'navigate') {
            window.location.href = currentAction.url;
            return;
        }

        if (currentAction.type === 'submit') {
            const form = document.createElement('form');
            form.method = currentAction.method || 'post';
            form.action = currentAction.action;
            if (currentAction.fields) {
                Object.keys(currentAction.fields).forEach(k => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = k;
                    input.value = currentAction.fields[k];
                    form.appendChild(input);
                });
            }
            document.body.appendChild(form);
            form.submit();
            return;
        }

        if (currentAction.type === 'submit-form') {
            currentAction.form.submit();
            return;
        }
    });
});
</script>
