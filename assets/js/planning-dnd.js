/* assets/js/planning-dnd.js */
/* Drag & drop assistito per Planning Turnar: mantiene il flusso rapido esistente. */
(function(){
    'use strict';

    function qs(selector, root){ return (root || document).querySelector(selector); }
    function qsa(selector, root){ return Array.prototype.slice.call((root || document).querySelectorAll(selector)); }

    function isPlanningPage(){
        return !!qs('.planning-layout') || !!qs('.dashboard-shell .planning-layout') || location.pathname.indexOf('/modules/turni/planning.php') !== -1;
    }

    function getOperatorCards(){
        return qsa('.operator-card');
    }

    function getDestinationTargets(){
        var cards = qsa('.destination-card');
        var zones = qsa('.destination-dropzone');
        return cards.concat(zones);
    }

    function getCardFromZone(target){
        return target.closest ? (target.closest('.destination-card') || target) : target;
    }

    function safeClick(el){
        if (!el) return;
        el.dispatchEvent(new MouseEvent('click', {bubbles:true, cancelable:true, view:window}));
    }

    function clearDragClasses(){
        qsa('.operator-card.dragging,.operator-card.drag-source-selected,.destination-card.drag-over,.destination-dropzone.drag-over').forEach(function(el){
            el.classList.remove('dragging','drag-source-selected','drag-over');
        });
    }

    function initOperatorDrag(){
        getOperatorCards().forEach(function(card){
            if (card.dataset.turnarDndReady === '1') return;
            card.dataset.turnarDndReady = '1';
            card.setAttribute('draggable', 'true');
            card.setAttribute('title', 'Trascina su una destinazione per assegnare rapidamente');

            card.addEventListener('dragstart', function(ev){
                window.TURNAR_DND_OPERATOR = card;
                card.classList.add('dragging','drag-source-selected');
                try {
                    ev.dataTransfer.effectAllowed = 'copy';
                    ev.dataTransfer.setData('text/plain', 'operator');
                } catch(e) {}
            });

            card.addEventListener('dragend', function(){
                window.TURNAR_DND_OPERATOR = null;
                clearDragClasses();
            });
        });
    }

    function initDestinationDrop(){
        getDestinationTargets().forEach(function(target){
            if (target.dataset.turnarDropReady === '1') return;
            target.dataset.turnarDropReady = '1';
            target.setAttribute('title', 'Rilascia qui un operatore per aprire assegnazione rapida');

            target.addEventListener('dragenter', function(ev){
                if (!window.TURNAR_DND_OPERATOR) return;
                ev.preventDefault();
                getCardFromZone(target).classList.add('drag-over');
                target.classList.add('drag-over');
            });

            target.addEventListener('dragover', function(ev){
                if (!window.TURNAR_DND_OPERATOR) return;
                ev.preventDefault();
                try { ev.dataTransfer.dropEffect = 'copy'; } catch(e) {}
            });

            target.addEventListener('dragleave', function(ev){
                if (target.contains(ev.relatedTarget)) return;
                getCardFromZone(target).classList.remove('drag-over');
                target.classList.remove('drag-over');
            });

            target.addEventListener('drop', function(ev){
                if (!window.TURNAR_DND_OPERATOR) return;
                ev.preventDefault();
                ev.stopPropagation();

                var operatorCard = window.TURNAR_DND_OPERATOR;
                var destinationCard = getCardFromZone(target);

                clearDragClasses();

                // Mantiene il flusso esistente: seleziona operatore e apre la quick assignment sulla destinazione.
                safeClick(operatorCard);
                setTimeout(function(){ safeClick(destinationCard); }, 80);
            });
        });
    }

    function init(){
        if (!isPlanningPage()) return;
        initOperatorDrag();
        initDestinationDrop();

        // Planning può filtrare/aggiornare elementi: ripasso leggero.
        var obs = new MutationObserver(function(){
            initOperatorDrag();
            initDestinationDrop();
        });
        obs.observe(document.body, {childList:true, subtree:true});
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
