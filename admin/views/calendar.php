<?php if ( ! defined('ABSPATH') ) exit; ?>
<div class="wrap impetus-ai-wrap">
<div class="impetus-header">
    <h1>📅 Tartalomnaptar</h1>
    <div style="display:flex;gap:8px;align-items:center;">
        <button class="button" id="btn-prev-month">‹</button>
        <strong id="month-label" style="min-width:160px;text-align:center;"></strong>
        <button class="button" id="btn-next-month">›</button>
        <a href="<?php echo admin_url('admin.php?page=impetus-ai-generate'); ?>" class="button button-primary">+ Uj poszt</a>
    </div>
</div>

<div class="impetus-card" style="padding:0;overflow:hidden;">
    <div class="impetus-cal-header">
        <?php foreach(['H','K','Sz','Cs','P','Sz','V'] as $d) : ?>
        <div><?php echo $d; ?></div>
        <?php endforeach; ?>
    </div>
    <div id="cal-grid" class="impetus-cal-grid"></div>
</div>

<div class="impetus-cal-legend">
    <span><span class="cal-dot" style="background:#1877f2;"></span> Facebook</span>
    <span><span class="cal-dot" style="background:#e1306c;"></span> Instagram</span>
    <span><span class="cal-dot" style="background:#0077b5;"></span> LinkedIn</span>
    <span><span class="cal-dot" style="background:#f0a500;"></span> Publikalva</span>
</div>

<script>
var currentDate = new Date();
var MONTHS = ['Januar','Februar','Marcius','Aprilis','Majus','Junius','Julius','Augusztus','Szeptember','Oktober','November','December'];
var PLATFORM_COLORS = {facebook:'#1877f2', instagram:'#e1306c', linkedin:'#0077b5'};

function loadCalendar() {
    var year  = currentDate.getFullYear();
    var month = currentDate.getMonth();
    document.getElementById('month-label').textContent = MONTHS[month] + ' ' + year;

    var monthStr = year + '-' + String(month+1).padStart(2,'0');

    jQuery.post(impetusAI.ajax_url, {
        action: 'impetus_calendar_data',
        nonce: impetusAI.nonce,
        month: monthStr,
    }, function(resp) {
        if (!resp.success) return;
        buildGrid(year, month, resp.data);
    });
}

function buildGrid(year, month, events) {
    var eventMap = {};
    events.forEach(function(e) {
        var date = (e.scheduled_at || '').split(' ')[0];
        if (!eventMap[date]) eventMap[date] = [];
        eventMap[date].push(e);
    });

    var firstDay  = new Date(year, month, 1);
    var lastDay   = new Date(year, month+1, 0);
    var startDow  = (firstDay.getDay() + 6) % 7;
    var todayStr  = new Date().toISOString().split('T')[0];
    var grid      = document.getElementById('cal-grid');
    grid.innerHTML = '';

    for (var i = 0; i < startDow; i++) {
        grid.appendChild(makeCell('', [], false));
    }

    for (var d = 1; d <= lastDay.getDate(); d++) {
        var dateStr = year + '-' + String(month+1).padStart(2,'0') + '-' + String(d).padStart(2,'0');
        grid.appendChild(makeCell(d, eventMap[dateStr] || [], dateStr === todayStr));
    }

    var total = startDow + lastDay.getDate();
    var rem   = (7 - (total % 7)) % 7;
    for (var i = 0; i < rem; i++) {
        grid.appendChild(makeCell('', [], false));
    }
}

function makeCell(day, events, isToday) {
    var cell = document.createElement('div');
    cell.className = 'cal-cell' + (isToday ? ' cal-today' : '');

    var dayEl = document.createElement('div');
    dayEl.className = 'cal-day';
    dayEl.textContent = day || '';
    cell.appendChild(dayEl);

    events.slice(0, 3).forEach(function(e) {
        var tag   = document.createElement('div');
        var color = e.status === 'published' ? '#f0a500' : (PLATFORM_COLORS[e.platform] || '#888');
        tag.className = 'cal-event';
        tag.style.cssText = 'background:'+color+'22;color:'+color+';border-left:2px solid '+color+';';
        var label = (e.topic || e.platform || '').substring(0, 18);
        if ((e.topic||'').length > 18) label += '…';
        tag.textContent = label;
        tag.title = (e.topic||'') + ' (' + e.platform + ') ' + (e.scheduled_at||'').substring(11,16);
        tag.onclick = function() {
            location.href = '<?php echo admin_url("admin.php?page=impetus-ai-posts&view="); ?>' + e.id;
        };
        cell.appendChild(tag);
    });

    if (events.length > 3) {
        var more = document.createElement('div');
        more.className = 'cal-more';
        more.textContent = '+' + (events.length - 3) + ' tovabbi';
        cell.appendChild(more);
    }

    return cell;
}

document.getElementById('btn-prev-month').onclick = function() {
    currentDate.setMonth(currentDate.getMonth() - 1);
    loadCalendar();
};
document.getElementById('btn-next-month').onclick = function() {
    currentDate.setMonth(currentDate.getMonth() + 1);
    loadCalendar();
};

loadCalendar();
</script>
</div>
