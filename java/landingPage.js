let currentSlide = 0;
const slides = document.querySelectorAll('.slide');
const indicators = document.querySelectorAll('.indicator');
const totalSlides = slides.length;

function showSlide(index) {
    slides.forEach((slide, i) => {
        slide.classList.toggle('active', i === index);
    });
    indicators.forEach((indicator, i) => {
        indicator.classList.toggle('active', i === index);
    });
}

function changeSlide(direction) {
    currentSlide = (currentSlide + direction + totalSlides) % totalSlides;
    showSlide(currentSlide);
}

function goToSlide(index) {
    currentSlide = index;
    showSlide(currentSlide);
}

// Fullscreen functionality
function openFullscreen(imageSrc) {
    const modal = document.getElementById('fullscreen-modal');
    const fullscreenImage = document.getElementById('fullscreen-image');
    
    fullscreenImage.src = imageSrc;
    modal.classList.add('active');
    
    // Prevent body scrolling when modal is open
    document.body.style.overflow = 'hidden';
}

function closeFullscreen() {
    const modal = document.getElementById('fullscreen-modal');
    modal.classList.remove('active');
    
    // Restore body scrolling
    document.body.style.overflow = 'auto';
}

// Close fullscreen when clicking outside the image
document.getElementById('fullscreen-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeFullscreen();
    }
});

// Close fullscreen with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeFullscreen();
    }
});

// Add click event listeners to all slide images using event delegation
document.addEventListener('DOMContentLoaded', function() {
    const slideshowContainer = document.querySelector('.slideshow');
    slideshowContainer.addEventListener('click', function(e) {
        if (e.target.tagName === 'IMG') {
            openFullscreen(e.target.src);
        }
    });
});

// Auto-advance slideshow every 6 seconds
setInterval(() => {
    changeSlide(1);
}, 10000);



// Search on Enter key
document.getElementById('searchInput').addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
        searchServices();
    }
});

function searchServices() {
    const input = document.getElementById('searchInput').value.trim().toLowerCase();
    const buttons = document.querySelectorAll('.service-btn');
    let anyMatch = false;
    buttons.forEach(btn => {
        const text = btn.textContent.toLowerCase();
        if (!input || text.includes(input)) {
            btn.style.display = '';
            anyMatch = true;
        } else {
            btn.style.display = 'none';
        }
    });
    // Optionally: Show a message if nothing matches
    // (not implemented here for simplicity)
}

function discoverWithLoading() {
    const loader = document.getElementById('loading-overlay');
    loader.style.display = 'flex';
    setTimeout(() => {
        window.location.href = 'gabaldonExplore.html';
    }, 1500); // 1.5-second delay to show the loader
}

// Mission/Vision Modal Functions
function openMissionVision() {
    document.getElementById('missionVisionModal').style.display = 'flex';
    // Prevent body scrolling when modal is open
    document.body.style.overflow = 'hidden';
}

function closeMissionVision() {
    document.getElementById('missionVisionModal').style.display = 'none';
    // Restore body scrolling
    document.body.style.overflow = 'auto';
}

// Close mission/vision modal when clicking outside the content
document.getElementById('missionVisionModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeMissionVision();
    }
});

// Close mission/vision modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.getElementById('missionVisionModal').style.display === 'flex') {
        closeMissionVision();
    }
});

// Real-time Clock Functionality
function updateClock() {
    // Get current time in Manila, Philippines (UTC+8)
    const options = {
        timeZone: 'Asia/Manila',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
    };
    
    const dateOptions = {
        timeZone: 'Asia/Manila',
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    };
    
    const now = new Date();
    document.getElementById('time').textContent = now.toLocaleTimeString('en-US', options);
    document.getElementById('date').textContent = now.toLocaleDateString('en-US', dateOptions);
}

// Update clock immediately and then every second
updateClock();
setInterval(updateClock, 1000);

// Add smooth entrance animation
window.addEventListener('load', () => {
    document.body.style.opacity = '0';
    document.body.style.transition = 'opacity 0.5s ease';
    setTimeout(() => {
        document.body.style.opacity = '1';
    }, 100);
});