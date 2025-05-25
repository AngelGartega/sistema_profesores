// Confirmaciones de eliminación
function confirmarEliminacion(id) {
    if(confirm('¿Estás seguro de eliminar este registro?')) {
        window.location.href = `crud.php?eliminar=${id}`;
    }
}

// Validación de formularios
document.addEventListener('DOMContentLoaded', () => {
    // Validar formulario de edición/creación
    const forms = document.querySelectorAll('form[data-validar]');
    
    forms.forEach(form => {
        form.addEventListener('submit', (e) => {
            const rfc = form.querySelector('input[name="rfc"]');
            const telefono = form.querySelector('input[name="telefono"]');
            
            // Validar RFC (Formato básico)
            if(!/^[A-Z]{4}\d{6}[A-Z0-9]{3}$/.test(rfc.value)) {
                e.preventDefault();
                alert('RFC inválido. Formato: 4 letras, 6 números, 3 caracteres');
                rfc.focus();
                return;
            }
            
            // Validar teléfono (opcional)
            if(telefono.value && !/^\d{10}$/.test(telefono.value)) {
                e.preventDefault();
                alert('Teléfono debe tener 10 dígitos');
                telefono.focus();
            }
        });
    });

    // Manejar subida de CSV
    const csvInput = document.querySelector('input[type="file"][accept=".csv"]');
    if(csvInput) {
        csvInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if(file) {
                const reader = new FileReader();
                
                reader.onload = (event) => {
                    const contenido = event.target.result;
                    // Validar primeras líneas
                    if(!contenido.includes('nombre,rfc,telefono,categoria')) {
                        alert('Formato de CSV inválido. Las columnas deben ser: nombre,rfc,telefono,categoria');
                        csvInput.value = '';
                    }
                };
                
                reader.readAsText(file);
            }
        });
    }
});

// Mostrar/ocultar mensajes
setTimeout(() => {
    const mensajes = document.querySelectorAll('.alert');
    mensajes.forEach(msg => {
        msg.style.opacity = '0';
        setTimeout(() => msg.remove(), 500);
    });
}, 5000);