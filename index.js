// Minimal client-side helpers
(function(){
    function $q(sel, ctx){ return (ctx || document).querySelector(sel); }
    function $qa(sel, ctx){ return Array.from((ctx || document).querySelectorAll(sel)); }

    // Confirm deletion forms use native confirm via onsubmit in markup.
    // Example small enhancement: attach AJAX to forms with data-ajax attribute
    $qa('form[data-ajax]').forEach(function(form){
        form.addEventListener('submit', function(e){
            e.preventDefault();
            var data = new FormData(form);
            fetch(form.action, { method: form.method || 'POST', body: data, credentials: 'same-origin' })
            .then(function(r){ return r.text(); })
            .then(function(text){
                alert('Done');
                // optionally refresh
                window.location.reload();
            })
            .catch(function(){ alert('Request failed'); });
        });
    });
})();
