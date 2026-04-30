/* assets/js/planning-dnd.js */
/* Drag & drop avanzato: operatori + spostamento turni tra cantieri e calendario */
(function(){
    'use strict';

    function qs(s,r){return (r||document).querySelector(s);} 
    function qsa(s,r){return Array.prototype.slice.call((r||document).querySelectorAll(s));}

    function isPlanningPage(){
        return location.pathname.indexOf('/modules/turni/') !== -1;
    }

    function extractTurnId(el){
        var id = el.dataset.turnId || el.dataset.id || '';
        if(id) return id;
        var a = el.closest('a');
        if(a && a.href){
            var m = a.href.match(/id=(\d+)/);
            if(m) return m[1];
        }
        return '';
    }

    function extractDestinationId(el){
        var id = el.dataset.destinationId || el.dataset.id || el.dataset.cantiereId || '';
        if(id) return id;
        return '';
    }

    function extractDate(el){
        return el.dataset.date || '';
    }

    function apiMove(turnId, destId, date, force){
        return fetch('/Turnar/modules/turni/move.php',{
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body:JSON.stringify({turno_id:turnId,id_cantiere:destId,data:date,force:force||false})
        }).then(r=>r.json());
    }

    function initTurnDrag(){
        qsa('.turn-chip, .calendar-turn').forEach(function(el){
            if(el.dataset.dndReady) return;
            el.dataset.dndReady=1;
            el.setAttribute('draggable','true');

            el.addEventListener('dragstart',function(e){
                window.TURNAR_DRAG_TURNO = el;
                el.classList.add('dragging');
                e.dataTransfer.setData('text/plain','turn');
            });

            el.addEventListener('dragend',function(){
                window.TURNAR_DRAG_TURNO=null;
                el.classList.remove('dragging');
            });
        });
    }

    function initDrop(){
        qsa('.destination-card, .destination-dropzone, .calendar-day, .calendar-cantiere').forEach(function(target){
            target.addEventListener('dragover',function(e){
                if(window.TURNAR_DRAG_TURNO){ e.preventDefault(); }
            });

            target.addEventListener('drop',function(e){
                if(!window.TURNAR_DRAG_TURNO) return;
                e.preventDefault();

                var turnoEl = window.TURNAR_DRAG_TURNO;
                var turnoId = extractTurnId(turnoEl);
                var destId = extractDestinationId(target);
                var date = extractDate(target);

                if(!turnoId){ alert('ID turno non trovato'); return; }

                apiMove(turnoId,destId,date,false).then(function(res){
                    if(res.success){ location.reload(); }
                    else if(res.requires_force){
                        if(confirm('Conflitto rilevato. Forzare spostamento?')){
                            apiMove(turnoId,destId,date,true).then(function(r2){
                                if(r2.success) location.reload();
                                else alert(r2.message||'Errore');
                            });
                        }
                    } else {
                        alert(res.message||'Errore');
                    }
                });
            });
        });
    }

    function init(){
        if(!isPlanningPage()) return;
        initTurnDrag();
        initDrop();
    }

    document.addEventListener('DOMContentLoaded',init);
})();
