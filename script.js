// Service Toggle Function
function openService(index) {
    const cards = document.querySelectorAll(".service-card");
    
    cards.forEach((card, i) => {
        if (i === index) {
            card.classList.toggle("active");
        } else {
            card.classList.remove("active");
        }
    });
}

// Smooth scrolling for navigation links
document.querySelectorAll('nav a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});