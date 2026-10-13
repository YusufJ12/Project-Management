<footer class="footer">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-sm-6 text-center text-sm-start">
                {{ $siteSetting->admin_footer_text ?? (date('Y') . ' © NexSphere Studio. All rights reserved.') }}
            </div>
            <div class="col-sm-6">
                <div class="text-sm-end text-center d-none d-sm-block text-muted">
                    {!! $siteSetting->admin_footer_crafted ?? ('Crafted with <i class="ri-heart-fill text-danger align-middle"></i> for NexSphere Studio') !!}
                </div>
            </div>
        </div>
    </div>
</footer>

