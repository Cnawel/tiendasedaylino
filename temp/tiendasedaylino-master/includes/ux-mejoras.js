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
        const currentPath = window.location.pathname.split('/').pop() || 'index.php';
        const currentHash = window.location.hash;
        const navLinks = document.querySelectorAll('.link-tienda');
        
        // Remover todas las clases activas primero
        navLinks.forEach(link => link.classList.remove('active-page'));
        
        // Buscar el enlace más específico que coincida
        let activeLink = null;
        let bestMatch = null;
        
        navLinks.forEach(link => {
            const href = link.getAttribute('href');
            if (!href) return;
            
            // Extraer página y hash del href
            const [linkPath, linkHash] = href.split('#');
            const linkPage = linkPath.split('/').pop() || 'index.php';
            
            // Si el enlace apunta a la página actual
            if (linkPage === currentPath) {
                // Si hay hash en ambos, deben coincidir
                if (linkHash && currentHash) {
                    if (linkHash === currentHash.replace('#', '')) {
                        activeLink = link;
                    }
                }
                // Si el enlace tiene hash pero la página actual no, no es activo
                else if (linkHash && !currentHash) {
                    // No hacer nada - enlaces con hash solo se activan cuando estás en esa sección
                }
                // Si no hay hash en el enlace y estamos en la página base
                else if (!linkHash && !currentHash) {
                    // Este es un match básico - solo lo usamos si no hay mejor match
                    if (!bestMatch) {
                        bestMatch = link;
                    }
                }
            }
        });
        
        // Usar el enlace más específico encontrado, o el mejor match básico
        const finalLink = activeLink || bestMatch;
        if (finalLink) {
            finalLink.classList.add('active-page');
        }
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
        // Variable global para rastrear si algún formulario está siendo enviado
        window.formSubmitting = false;
        
        // Objeto global para rastrear el estado de modificación de cada formulario
        // Usar window para que sea accesible desde el listener beforeunload
        if (!window._formsState) {
            window._formsState = new WeakMap();
        }
        const formsState = window._formsState;
        
        const forms = document.querySelectorAll('form[data-protect]');
        
        // Si no hay formularios, no hacer nada
        if (forms.length === 0) {
            return;
        }
        
        // Inicializar estado de cada formulario
        forms.forEach(form => {
            // Solo inicializar si no tiene estado previo
            if (!formsState.has(form)) {
                formsState.set(form, { modified: false });
            }
            
            // Detectar cambios en los campos del formulario
            form.querySelectorAll('input, textarea, select').forEach(input => {
                // Evitar agregar múltiples listeners al mismo input
                if (!input.hasAttribute('data-protection-listener')) {
                    input.setAttribute('data-protection-listener', 'true');
                    input.addEventListener('change', () => {
                        const state = formsState.get(form);
                        if (state) {
                            state.modified = true;
                        }
                    });
                }
            });
            
            // Detectar cuando el formulario se está enviando
            // Usar capture: true para ejecutar ANTES de otros listeners
            // Evitar agregar múltiples listeners al mismo formulario
            if (!form.hasAttribute('data-submit-listener')) {
                form.setAttribute('data-submit-listener', 'true');
                form.addEventListener('submit', function(e) {
                    // Marcar inmediatamente que un formulario se está enviando
                    window.formSubmitting = true;
                    
                    // Agregar clase submitted para compatibilidad con código existente
                    form.classList.add('submitted');
                    
                    // Actualizar estado del formulario
                    const state = formsState.get(form);
                    if (state) {
                        state.modified = false; // Ya no está modificado, se está enviando
                    }
                }, true); // capture: true para ejecutar antes
            }
        });
        
        // Un solo listener beforeunload global para todos los formularios
        // Solo agregarlo una vez, no por cada formulario
        if (!window._formProtectionListenerAdded) {
            window.addEventListener('beforeunload', function(e) {
                // Si un formulario se está enviando, no prevenir la navegación
                if (window.formSubmitting) {
                    return;
                }
                
                // Verificar si algún formulario con data-protect modificado no ha sido enviado
                // Buscar formularios en tiempo real para manejar cambios dinámicos del DOM
                const protectedForms = document.querySelectorAll('form[data-protect]');
                let hasModifiedForm = false;
                
                protectedForms.forEach(form => {
                    const state = formsState.get(form);
                    // Si el formulario tiene estado y está modificado, verificar si no tiene la clase submitted
                    if (state && state.modified && !form.classList.contains('submitted')) {
                        hasModifiedForm = true;
                    }
                });
                
                // Solo prevenir la navegación si hay un formulario modificado
                if (hasModifiedForm) {
                    e.preventDefault();
                    e.returnValue = '¿Seguro que quieres salir? Los cambios no guardados se perderán.';
                    return e.returnValue;
                }
            });
            
            // Marcar que el listener ya fue agregado
            window._formProtectionListenerAdded = true;
        }
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
        
        // Actualizar enlace activo cuando cambia el hash (scroll a secciones)
        window.addEventListener('hashchange', highlightActivePage);
        
        // También actualizar cuando se hace scroll (para secciones con hash)
        let scrollTimeout;
        window.addEventListener('scroll', function() {
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(function() {
                // Solo actualizar si hay un hash en la URL
                if (window.location.hash) {
                    highlightActivePage();
                }
            }, 100);
        });
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

