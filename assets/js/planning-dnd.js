/* assets/js/planning-dnd.js */
/* Drag & drop Turnar: operatori parcheggiati nel cantiere + spostamento turni esistenti */
(function(){
    'use strict';

    function qs(s,r){ return (r||document).querySelector(s); }
    function qsa(s,r){ return Array.prototype.slice.call((r||document).querySelectorAll(s)); }

    function isTurniPage(){
        return location.pathname.indexOf('/modules/turni/') !== -1;
    }

    function closest(el, selector){
        return el && el.closest ? el.closest(selector) : null;
    }

    function getText(el){
        return el ? (el.textContent || '').replace(/\s+/g,' ').trim() : '';
    }

    function getOperatorName(card){
        return getText(qs('.operator-name', card)) || getText(card).split('\n')[0] || 'Operatore';
    }

    function extractOperatorId(card){
        return card.dataset.operatorId || card.dataset.id || card.dataset.dipendenteId || '';
    }

    function extractTurnId(el){
        var id = el.dataset.turnId || el.dataset.id || '';
        if(id) return id;
        var a = closest(el, 'a');
        if(a && a.href){
            var m = a.href.match(/[?&]id=(\d+)/);
            if(m) return m[1];
        }
        return '';
    }

    function extractDestinationId(el){
        var node = closest(el, '.destination-card,.destination-dropzone,.calendar-cantiere,.calendar-day') || el;
        var id = node.dataset.destinationId || node.dataset.cantiereId || node.dataset.id || '';
        if(id) return id;

        var a = qs('a[href*="id_cantiere="]', node) || qs('a[href*="cantiere_id="]', node);
        if(a && a.href){
            var m = a.href.match(/[?&](?:id_cantiere|cantiere_id)=(\d+)/);
            if(m) return m[1];
        }
        return '';
    }

    function extractDate(el){
        var node = closest(el, '.calendar-day,.destination-card,.destination-dropzone,.calendar-cantiere') || el;
        var date = node.dataset.date || node.dataset.data || '';
        if(date) return date;
        var a = qs('a[href*="data="]', node) || qs('a[href*="date="]', node);
        if(a && a.href){
            var m = a.href.match(/[?&](?:data|date)=(\d{4}-\d{2}-\d{2})/);
            if(m) return m[1];
        }
        return '';
    }

    function apiMove(turnId, destId, date, force){
        return fetch('/Turnar/modules/turni/move.php',{
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body:JSON.stringify({turno_id:turnId,id_cantiere:destId,data:date,force:!!force})
        }).then(function(r){ return r.json(); });
    }

    function clickRealDestination(destinationCard){
        destinationCard.dispatchEvent(new MouseEvent('click', {bubbles:true, cancelable:true, view:window}));
    }

    function clickRealOperator(operatorCard){
        operatorCard.dispatchEvent(new MouseEvent('click', {bubbles:true, cancelable:true, view:window}));
    }

    function ensurePendingBox(destinationCard){
        var box = qs('.turnar-pending-box', destinationCard);
        if(box) return box;

        var zone = qs('.destination-dropzone', destinationCard) || destinationCard;
        box = document.createElement('div');
        box.className = 'turnar-pending-box';
        box.innerHTML = '<div class="turnar-pending-title">Da configurare</div>';
        zone.appendChild(box);
        return box;
    }

    function addPendingOperator(operatorCard, destinationCard){
        var operatorId = extractOperatorId(operatorCard);
        var name = getOperatorName(operatorCard);
        var box = ensurePendingBox(destinationCard);

        if(operatorId && qs('.turnar-pending-chip[data-operator-id="'+operatorId+'"]', box)){
            return;
        }

        var chip = document.createElement('button');
        chip.type = 'button';
        chip.className = 'turnar-pending-chip';
        chip.dataset.operatorId = operatorId;
        chip.innerHTML = '<span>👤 '+name+'</span><small>clicca per orario</small>';

        chip.addEventListener('click', function(ev){
            ev.preventDefault();
            ev.stopPropagation();
            clickRealOperator(operatorCard);
            setTimeout(function(){ clickRealDestination(destinationCard); }, 80);
        });

        box.appendChild(chip);
    }

    function clearVisual(){
        qsa('.dragging,.drag-over,.drag-source-selected').forEach(function(el){
            el.classList.remove('dragging','drag-over','drag-source-selected');
        });
    }

    function initOperatorDrag(){
        qsa('.operator-card').forEach(function(card){
            if(card.dataset.operatorDndReady) return;
            card.dataset.operatorDndReady = '1';
            card.setAttribute('draggable','true');
            card.setAttribute('title','Trascina su un cantiere: verrà parcheggiato e configurerai l’orario dopo');

            card.addEventListener('dragstart', function(e){
                window.TURNAR_DRAG_OPERATOR = card;
                card.classList.add('dragging','drag-source-selected');
                try{ e.dataTransfer.effectAllowed = 'copy'; e.dataTransfer.setData('text/plain','operator'); }catch(err){}
            });
            card.addEventListener('dragend', function(){
                window.TURNAR_DRAG_OPERATOR = null;
                clearVisual();
            });
        });
    }

    function initTurnDrag(){
        qsa('.turn-chip, .calendar-turn').forEach(function(el){
            if(el.dataset.turnDndReady) return;
            el.dataset.turnDndReady = '1';
            el.setAttribute('draggable','true');
            el.setAttribute('title','Trascina per spostare il turno');

            el.addEventListener('dragstart', function(e){
                window.TURNAR_DRAG_TURNO = el;
                el.classList.add('dragging');
                try{ e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain','turn'); }catch(err){}
            });
            el.addEventListener('dragend', function(){
                window.TURNAR_DRAG_TURNO = null;
                clearVisual();
            });
        });
    }

    function handleTurnDrop(target){
        var turnoEl = window.TURNAR_DRAG_TURNO;
        var turnoId = extractTurnId(turnoEl);
        var destId = extractDestinationId(target);
        var date = extractDate(target);

        if(!turnoId){ alert('ID turno non trovato.'); return; }
        if(!destId && !date){ alert('Destinazione/data non trovata nel punto di rilascio.'); return; }

        apiMove(turnoId, destId, date, false).then(function(res){
            if(res.success){ location.reload(); return; }
            if(res.requires_force){
                if(confirm('Conflitto rilevato. Forzare spostamento?')){
                    apiMove(turnoId, destId, date, true).then(function(r2){
                        if(r2.success) location.reload();
                        else alert(r2.message || 'Errore spostamento.');
                    });
                }
                return;
            }
            alert(res.message || 'Errore spostamento.');
        }).catch(function(){
            alert('Errore rete durante lo spostamento.');
        });
    }

    function initDrops(){
        qsa('.destination-card, .destination-dropzone, .calendar-day, .calendar-cantiere').forEach(function(target){
            if(target.dataset.dropReady) return;
            target.dataset.dropReady = '1';

            target.addEventListener('dragover', function(e){
                if(window.TURNAR_DRAG_OPERATOR || window.TURNAR_DRAG_TURNO){
                    e.preventDefault();
                    target.classList.add('drag-over');
                }
            });

            target.addEventListener('dragleave', function(e){
                if(target.contains(e.relatedTarget)) return;
                target.classList.remove('drag-over');
            });

            target.addEventListener('drop', function(e){
                if(!window.TURNAR_DRAG_OPERATOR && !window.TURNAR_DRAG_TURNO) return;
                e.preventDefault();
                e.stopPropagation();
                target.classList.remove('drag-over');

                if(window.TURNAR_DRAG_OPERATOR){
                    var destinationCard = closest(target, '.destination-card') || target;
                    if(!destinationCard.classList.contains('destination-card')) return;
                    addPendingOperator(window.TURNAR_DRAG_OPERATOR, destinationCard);
                    return;
                }

                if(window.TURNAR_DRAG_TURNO){
                    handleTurnDrop(target);
                }
            });
        });
    }

    function init(){
        if(!isTurniPage()) return;
        initOperatorDrag();
        initTurnDrag();
        initDrops();

        var obs = new MutationObserver(function(){
            initOperatorDrag();
            initTurnDrag();
            initDrops();
        });
        obs.observe(document.body,{childList:true,subtree:true});
    }

    if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
