/**
 * ========================================================================
 * UX MEJORAS SIMPLES - Seda y Lino
 * ========================================================================
 * Mejoras de experiencia de usuario simples y efectivas
 * Sin complicaciones, solo lo esencial
 */

(function() {
    'use strict';
    
    // ====================================================================
    // 1. BOTÓN VOLVER ARRIBA - Simple y discreto
    // ====================================================================
    function initScrollToTop() {
        // Crear botón si no existe
        let btnScrollTop = document.querySelector('.btn-scroll-top');
        if (!btnScrollTop) {
            btnScrollTop = document.createElement('button');
            btnScrollTop.className = 'btn-scroll-top';
            btnScrollTop.innerHTML = '↑';
            btnScrollTop.setAttribute('aria-label', 'Volver arriba');
            btnScrollTop.setAttribute('title', 'Volver arriba');
            document.body.appendChild(btnScrollTop);
        }
        
        // Mostrar/ocultar según scroll
        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 300) {
                btnScrollTop.classList.add('show');
            } else {
                btnScrollTop.classList.remove('show');
            }
        });
        
        // Scroll suave al hacer clic
        btnScrollTop.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }
    
    // ====================================================================
    // 2. INDICADOR DE PÁGINA ACTIVA EN NAVBAR
    // ====================================================================
    function highlightActivePage() {
        const currentPage = window.location.pathname.split('/').pop() || 'index.php';
        const navLinks = document.querySelectorAll('.link-tienda');
        
        navLinks.forEach(link => {
            const linkPage = link.getAttribute('href');
            if (linkPage && linkPage.includes(currentPage)) {
                link.classList.add('active-page');
            }
        });
    }
    
    // ====================================================================
    // 3. FILTROS ACTIVOS EN CATÁLOGO
    // ====================================================================
    function highlightActiveFilter() {
        const urlParams = new URLSearchParams(window.location.search);
        const categoria = urlParams.get('categoria') || 'todos';
        
        const filtros = document.querySelectorAll('.filtro-categoria');
        filtros.forEach(filtro => {
            const href = filtro.getAttribute('href');
            if (href && href.includes('categoria=' + categoria)) {
                filtro.classList.add('active');
            }
        });
    }
    
    // ====================================================================
    // 4. LAZY LOADING SIMPLE PARA IMÁGENES
    // ====================================================================
    function initLazyLoading() {
        const images = document.querySelectorAll('img[data-src]');
        
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver(function(entries, observer) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.remove('skeleton');
                        imageObserver.unobserve(img);
                    }
                });
            });
            
            images.forEach(function(img) {
                img.classList.add('skeleton');
                imageObserver.observe(img);
            });
        } else {
            // Fallback para navegadores antiguos
            images.forEach(function(img) {
                img.src = img.dataset.src;
            });
        }
    }
    
    // ====================================================================
    // 5. CONFIRMAR ANTES DE SALIR DE FORMULARIO CON DATOS
    // ====================================================================
    function initFormProtection() {
        const forms = document.querySelectorAll('form[data-protect]');
        
        forms.forEach(form => {
            let formModified = false;
            
            form.querySelectorAll('input, textarea, select').forEach(input => {
                input.addEventListener('change', () => formModified = true);
            });
            
            window.addEventListener('beforeunload', function(e) {
                if (formModified && !form.classList.contains('submitted')) {
                    e.preventDefault();
                    e.returnValue = '¿Seguro que quieres salir? Los cambios no guardados se perderán.';
                }
            });
            
            form.addEventListener('submit', () => {
                form.classList.add('submitted');
            });
        });
    }
    
    // ====================================================================
    // 6. FEEDBACK VISUAL AL HACER CLIC EN LINKS EXTERNOS
    // ====================================================================
    function initExternalLinks() {
        document.querySelectorAll('a[href^="http"]').forEach(link => {
            if (!link.hostname.includes(window.location.hostname)) {
                link.setAttribute('target', '_blank');
                link.setAttribute('rel', 'noopener noreferrer');
                
                // Agregar icono visual (opcional)
                if (!link.querySelector('.external-icon')) {
                    const icon = document.createElement('span');
                    icon.className = 'external-icon';
                    icon.innerHTML = ' ↗';
                    icon.style.fontSize = '0.8em';
                    link.appendChild(icon);
                }
            }
        });
    }
    
    // ====================================================================
    // 7. ANIMACIÓN SUAVE AL APARECER ELEMENTOS
    // ====================================================================
    function initScrollAnimations() {
        const elements = document.querySelectorAll('.animate-on-scroll');
        
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, { threshold: 0.1 });
            
            elements.forEach(function(el) {
                el.style.opacity = '0';
                el.style.transform = 'translateY(20px)';
                el.style.transition = 'all 0.6s ease';
                observer.observe(el);
            });
        }
    }
    
    // ====================================================================
    // 8. TOOLTIP SIMPLE EN HOVER
    // ====================================================================
    function initTooltips() {
        const tooltipElements = document.querySelectorAll('[data-tooltip]');
        
        tooltipElements.forEach(el => {
            el.addEventListener('mouseenter', function() {
                // El CSS ya maneja el tooltip, solo agregamos la clase para control
                this.classList.add('tooltip-active');
            });
            
            el.addEventListener('mouseleave', function() {
                this.classList.remove('tooltip-active');
            });
        });
    }
    
    // ====================================================================
    // 9. NAVBAR CON SOMBRA AL HACER SCROLL
    // ====================================================================
    function initStickyNavbar() {
        const navbar = document.querySelector('.navbar');
        
        if (!navbar) return;
        
        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
    }
    
    // ====================================================================
    // 10. ANIMACIÓN FADE-IN PARA TARJETAS AL CARGAR
    // ====================================================================
    function initCardAnimations() {
        const cards = document.querySelectorAll('.tarjeta, .producto-card');
        
        cards.forEach((card, index) => {
            // Añadir delay progresivo para efecto cascada
            setTimeout(() => {
                card.classList.add('fade-in');
            }, index * 100);
        });
    }
    
    // ====================================================================
    // INICIALIZAR TODAS LAS MEJORAS AL CARGAR LA PÁGINA
    // ====================================================================
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🎨 Inicializando mejoras UX...');
        
        // Ejecutar todas las funciones
        initScrollToTop();
        highlightActivePage();
        highlightActiveFilter();
        initLazyLoading();
        initFormProtection();
        initExternalLinks();
        initScrollAnimations();
        initTooltips();
        initStickyNavbar();
        initCardAnimations();
        
        console.log('✅ Mejoras UX cargadas correctamente');
    });
    
    // ====================================================================
    // FUNCIÓN HELPER: MOSTRAR LOADING SIMPLE
    // ====================================================================
    window.showLoading = function() {
        let overlay = document.querySelector('.loading-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'loading-overlay';
            overlay.innerHTML = '<div class="loading-spinner"></div>';
            document.body.appendChild(overlay);
        }
        overlay.classList.add('show');
    };
    
    window.hideLoading = function() {
        const overlay = document.querySelector('.loading-overlay');
        if (overlay) {
            overlay.classList.remove('show');
        }
    };
    
})();



