document.querySelectorAll('[data-t3cq-loader-msg]').forEach(function(el) {
    el.addEventListener('click', function() {
        var overlay = document.getElementById('t3cq-overlay');
        document.getElementById('t3cq-overlay-label').textContent = el.getAttribute('data-t3cq-loader-msg') || 'Working...';
        overlay.style.display = 'flex';
        el.style.pointerEvents = 'none';
        el.style.opacity = '0.6';
    });
});
