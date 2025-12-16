document.addEventListener('DOMContentLoaded', function() {
    var heroCarouselElement = document.getElementById('heroCarousel');
    if (heroCarouselElement) {
        var heroCarousel = new bootstrap.Carousel(heroCarouselElement, {
            interval: 3000, // Rotate every 3 seconds
            ride: 'carousel'
        });

        // Redirect to catalogo.php on carousel item click
        heroCarouselElement.querySelectorAll('.carousel-item a').forEach(item => {
            item.addEventListener('click', function(event) {
                event.preventDefault(); // Prevent default link behavior
                window.location.href = 'catalogo.php'; // Redirect to catalogo.php
            });
        });
    }
});
