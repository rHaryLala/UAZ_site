document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        document.querySelector(this.getAttribute('href')).scrollIntoView({
            behavior: 'smooth'
        });
    });
});

const gridItems = document.querySelectorAll('.grid-item');
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.opacity = 1;
            entry.target.style.transform = 'translateY(0)';
        }
    });
}, { threshold: 0.5 });

gridItems.forEach(item => {
    item.style.opacity = 0;
    item.style.transform = 'translateY(20px)';
    item.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
    observer.observe(item);
});

const stats = document.querySelectorAll('.stat-number');
const statsObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            const target = parseInt(entry.target.textContent);
            let count = 0;
            const timer = setInterval(() => {
                count += Math.ceil(target / 100);
                if (count >= target) {
                    clearInterval(timer);
                    entry.target.textContent = target;
                } else {
                    entry.target.textContent = count;
                }
            }, 20);
        }
    });
}, { threshold: 1, rootMargin: '0px 0px -100px 0px' });

stats.forEach(stat => {
    statsObserver.observe(stat);
});