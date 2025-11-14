<?php
/**
 * ========================================================================
 * CUENTA ELIMINADA - Tienda Seda y Lino
 * ========================================================================
 * Página de despedida tras eliminación de cuenta
 * Muestra mensaje cálido y amigable, con redirección automática a inicio
 * ========================================================================
 */

// Configurar título de la página
$titulo_pagina = 'Hasta Pronto';

// Incluir header completo (head + navigation)
include 'includes/header.php';
?>

<style>
    /* Estilos para la página de despedida */
    .farewell-container {
        min-height: calc(100vh - 200px);
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        padding: 2rem 1rem;
        background: linear-gradient(135deg, var(--color-bg-light) 0%, var(--color-bg-white) 100%);
    }
    
    .farewell-card {
        max-width: 650px;
        width: 100%;
        background: var(--color-bg-white);
        border-radius: 16px;
        box-shadow: 0 8px 30px rgba(139, 139, 122, 0.15);
        padding: 3rem 2.5rem;
        animation: fadeInUp 0.8s ease-out;
        text-align: center;
    }
    
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .farewell-icon {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--color-primary) 0%, #9B9B8B 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 2rem;
        box-shadow: 0 10px 30px rgba(139, 139, 122, 0.3);
        animation: pulse 2s ease-in-out infinite;
    }
    
    @keyframes pulse {
        0%, 100% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.05);
        }
    }
    
    .farewell-icon i {
        font-size: 3.5rem;
        color: white;
    }
    
    .farewell-title {
        font-size: 2.5rem;
        font-weight: 700;
        color: var(--color-primary);
        margin-bottom: 1rem;
        font-family: "Quicksand", sans-serif;
    }
    
    .farewell-message {
        font-size: 1.25rem;
        color: var(--color-text-main);
        line-height: 1.8;
        margin-bottom: 2rem;
        font-weight: 400;
    }
    
    .farewell-submessage {
        font-size: 1.1rem;
        color: var(--color-text-secondary);
        line-height: 1.6;
        margin-bottom: 2.5rem;
        font-style: italic;
    }
    
    .info-box {
        background: linear-gradient(135deg, #FFF9E6 0%, #FFFBF0 100%);
        border: 2px solid var(--color-warning);
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        text-align: left;
    }
    
    .info-box h5 {
        color: #856404;
        font-weight: 600;
        margin-bottom: 0.75rem;
        display: flex;
        align-items: center;
        font-size: 1.1rem;
    }
    
    .info-box h5 i {
        margin-right: 0.5rem;
        font-size: 1.2rem;
    }
    
    .info-box p {
        color: #856404;
        margin-bottom: 0.5rem;
        line-height: 1.6;
        font-size: 0.95rem;
    }
    
    .info-box ul {
        color: #856404;
        margin-bottom: 0;
        padding-left: 1.5rem;
        font-size: 0.95rem;
    }
    
    .info-box ul li {
        margin-bottom: 0.5rem;
    }
    
    .countdown-box {
        background: var(--color-bg-light);
        border: 2px dashed var(--color-primary);
        border-radius: 10px;
        padding: 1rem;
        margin-bottom: 2rem;
        text-align: center;
    }
    
    .countdown-text {
        font-size: 1rem;
        color: var(--color-text-secondary);
        margin-bottom: 0.5rem;
    }
    
    .countdown-number {
        font-size: 2rem;
        font-weight: 700;
        color: var(--color-primary);
        font-family: "Quicksand", sans-serif;
    }
    
    .action-buttons {
        display: flex;
        gap: 1rem;
        justify-content: center;
        flex-wrap: wrap;
    }
    
    .btn-farewell {
        background: var(--color-primary);
        color: white;
        border: none;
        border-radius: 25px;
        padding: 12px 30px;
        font-weight: 600;
        font-family: "Quicksand", sans-serif;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(139, 139, 122, 0.3);
    }
    
    .btn-farewell:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(139, 139, 122, 0.4);
        color: white;
    }
    
    .btn-farewell-outline {
        background: transparent;
        color: var(--color-primary);
        border: 2px solid var(--color-primary);
        border-radius: 25px;
        padding: 12px 30px;
        font-weight: 600;
        font-family: "Quicksand", sans-serif;
        transition: all 0.3s ease;
    }
    
    .btn-farewell-outline:hover {
        background: var(--color-primary);
        color: white;
        transform: translateY(-2px);
    }
    
    @media (max-width: 768px) {
        .farewell-card {
            padding: 2rem 1.5rem;
        }
        
        .farewell-title {
            font-size: 2rem;
        }
        
        .farewell-message {
            font-size: 1.1rem;
        }
        
        .farewell-submessage {
            font-size: 1rem;
        }
        
        .farewell-icon {
            width: 100px;
            height: 100px;
        }
        
        .farewell-icon i {
            font-size: 3rem;
        }
        
        .action-buttons {
            flex-direction: column;
        }
        
        .action-buttons .btn {
            width: 100%;
        }
    }
</style>

<main>
    <div class="farewell-container">
        <div class="farewell-card">
            <div class="farewell-icon">
                <i class="fas fa-heart"></i>
            </div>
            
            <h1 class="farewell-title">¡Hasta Pronto!</h1>
            
            <p class="farewell-message">
                Tu cuenta ha sido desactivada correctamente.
            </p>
            
            <p class="farewell-submessage">
                Te esperamos nuevamente cuando quieras volver. Fue un placer tenerte con nosotros.
            </p>
            
            <div class="countdown-box">
                <div class="countdown-text">Serás redirigido al inicio en:</div>
                <div class="countdown-number" id="countdown">10</div>
                <div class="countdown-text">segundos</div>
            </div>
            
            <div class="info-box">
                <h5>
                    <i class="fas fa-info-circle"></i>
                    Período de Gracia de 30 Días
                </h5>
                <p>
                    Tu cuenta quedará <strong>desactivada durante 30 días</strong> y luego se procederá a borrarla por completo.
                </p>
                <p>
                    <strong>¿Puedes reactivar tu cuenta?</strong>
                </p>
                <ul>
                    <li>Sí, puedes reactivar tu cuenta en cualquier momento dentro de los próximos 30 días.</li>
                    <li>Para reactivarla, simplemente inicia sesión con tu correo electrónico y contraseña.</li>
                    <li>Al iniciar sesión, tu cuenta se reactivará automáticamente.</li>
                    <li>Después de 30 días, la eliminación será permanente e irreversible.</li>
                </ul>
            </div>
            
            <div class="action-buttons">
                <a href="index.php" class="btn btn-farewell" id="btnRedirect">
                    <i class="fas fa-home me-2"></i>Volver al Inicio Ahora
                </a>
                <a href="login.php" class="btn btn-farewell-outline">
                    <i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión
                </a>
            </div>
        </div>
    </div>
</main>

<script>
    // Auto-redirect con countdown
    let countdown = 10; // Segundos hasta redirección
    const countdownElement = document.getElementById('countdown');
    const btnRedirect = document.getElementById('btnRedirect');
    
    // Función para actualizar el countdown
    function updateCountdown() {
        countdownElement.textContent = countdown;
        
        if (countdown <= 0) {
            // Redirigir a index.php
            window.location.href = 'index.php';
            return;
        }
        
        countdown--;
        setTimeout(updateCountdown, 1000);
    }
    
    // Iniciar countdown cuando la página carga
    document.addEventListener('DOMContentLoaded', function() {
        updateCountdown();
    });
    
    // Actualizar texto del botón cuando queda poco tiempo
    setInterval(function() {
        if (countdown <= 3 && countdown > 0) {
            btnRedirect.innerHTML = '<i class="fas fa-home me-2"></i>Volver al Inicio (' + countdown + 's)';
        }
    }, 1000);
</script>

<?php include 'includes/footer.php'; render_footer(); ?>
