document.querySelectorAll('[data-t3cq-analyse-btn]').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var label = btn.querySelector('[data-t3cq-analyse-label]');
        var spinner = btn.querySelector('[data-t3cq-analyse-spinner]');
        if (label) label.textContent = 'Analysing…';
        if (spinner) spinner.style.display = 'inline-block';
        btn.style.pointerEvents = 'none';
        btn.style.opacity = '0.7';
    });
});
