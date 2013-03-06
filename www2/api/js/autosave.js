var oldTitle = '';
var oldContent = '';

function autosave() {
    var title = $('.saveTitle').val();
    var content = $('.saveContent').val();
    if (title != oldTitle || content != oldContent) {
        oldTitle = title;
        oldContent = content;
        $.post('/api/autosaver/save.json', {
            title: title,
            content: content,
        });
    }
}

$(document).ready(function() {
    oldTitle = $('.saveTitle').val();
    oldContent = $('.saveContent').val();
    window.setInterval('autosave();', 2000);
});
