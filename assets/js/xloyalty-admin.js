document.addEventListener('DOMContentLoaded', function () {
    var registerButton = document.getElementById('xloyalty_register');
    if (!registerButton) return;

    registerButton.addEventListener('click', function () {
        var ids = [
            'company_name',
            'eshop_url',
            'contact_name',
            'business_phone',
            'email',
            'street',
            'city'
        ];
        ids.forEach(function (id) {
            var el = document.getElementById(id);
            if (el) {
                el.setAttribute('required', 'required');
            }
        });
    });
});