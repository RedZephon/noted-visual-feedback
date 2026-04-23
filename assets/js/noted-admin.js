(function () {
    document.querySelectorAll('#adminmenu a[href*="/?noted"], #adminmenu a[href*="wpnoted.com"]').forEach(function (a) {
        a.setAttribute('target', '_blank');
        a.setAttribute('rel', 'noopener');
    });

    var btn = document.getElementById('noted-reset-walkthrough');
    if (!btn || typeof NotedAdmin === 'undefined') {
        return;
    }

    var labels = NotedAdmin.i18n || {};

    function setLabel(text, color) {
        btn.textContent = text;
        btn.style.color = color || '';
        btn.style.borderColor = color || '';
    }

    btn.addEventListener('click', function () {
        btn.disabled = true;
        setLabel(labels.resetting || 'Resetting...');

        fetch(NotedAdmin.walkthroughUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': NotedAdmin.nonce
            },
            credentials: 'same-origin',
            body: JSON.stringify({ completed: false })
        }).then(function (res) {
            if (res.ok) {
                setLabel(labels.done || 'Done', '#D4920A');
                setTimeout(function () {
                    setLabel(labels.reset || 'Reset walkthrough');
                    btn.disabled = false;
                }, 3000);
            } else {
                setLabel(labels.error || 'Error, please try again', '#ef5350');
                btn.disabled = false;
                setTimeout(function () {
                    setLabel(labels.reset || 'Reset walkthrough');
                }, 3000);
            }
        }).catch(function () {
            setLabel(labels.error || 'Error, please try again', '#ef5350');
            btn.disabled = false;
        });
    });
})();
