function aplicarMascaraCpf(input) {
    if (!input) return;
    input.addEventListener('input', function(e) {
        let valor = e.target.value.replace(/\D/g, '');
        if (valor.length > 11) valor = valor.slice(0, 11);
        valor = valor.replace(/(\d{3})(\d)/, '$1.$2');
        valor = valor.replace(/(\d{3})(\d)/, '$1.$2');
        valor = valor.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        e.target.value = valor;
    });
}

function aplicarMascaraCnpj(input) {
    if (!input) return;
    input.addEventListener('input', function(e) {
        let valor = e.target.value.replace(/\D/g, '');
        if (valor.length > 14) valor = valor.slice(0, 14);
        valor = valor.replace(/^(\d{2})(\d)/, '$1.$2');
        valor = valor.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
        valor = valor.replace(/\.(\d{3})(\d)/, '.$1/$2');
        valor = valor.replace(/(\d{4})(\d)/, '$1-$2');
        e.target.value = valor;
    });
}

function aplicarMascaraCep(input) {
    if (!input) return;
    input.addEventListener('input', function(e) {
        let valor = e.target.value.replace(/\D/g, '');
        if (valor.length > 8) valor = valor.slice(0, 8);
        valor = valor.replace(/(\d{5})(\d)/, '$1-$2');
        e.target.value = valor;
    });
}

function aplicarMascaraTelefone(input) {
    if (!input) return;
    input.addEventListener('input', function(e) {
        let valor = e.target.value.replace(/\D/g, '');
        if (valor.length > 11) valor = valor.slice(0, 11);
        if (valor.length > 10) {
            valor = valor.replace(/^(\d{2})(\d{5})(\d{4})$/, '($1) $2-$3');
        } else if (valor.length > 5) {
            valor = valor.replace(/^(\d{2})(\d{4})(\d{0,4})$/, '($1) $2-$3');
        } else if (valor.length > 2) {
            valor = valor.replace(/^(\d{2})(\d{0,5})$/, '($1) $2');
        }
        e.target.value = valor;
    });
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[id$="-input"][id*="cpf"]').forEach(aplicarMascaraCpf);
    document.querySelectorAll('input[name="cpf"]').forEach(aplicarMascaraCpf);

    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        if (alert.classList.contains('alert-success') || alert.classList.contains('alert-info')) {
            setTimeout(function() {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 500);
            }, 5000);
        }
    });
});
