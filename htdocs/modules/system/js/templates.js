$(document).ready(
    function () {
        $('#fileTree').fileTree({
            script: 'admin/tplsets/jquery.php?op=tpls_display_folder',
            folderEvent: 'click',
            expandSpeed: 750,
            collapseSpeed: 750,
            multiFolder: false
        });
    });


//Edit
function tpls_edit_file(path_file, path, file, extension) {
    $('#display_contenu').hide();
    $('#display_form').hide();
    $('#loading').show();
    $.ajax({
        type: "POST",
        url: "./admin/tplsets/jquery.php",
        // Object form: jQuery encodes each value once - concatenation
        // corrupted paths containing &, +, or =. No "file" field: the
        // endpoint derives the name from the validated path itself.
        data: {op: "tpls_edit_file", path_file: path_file},
        success: function (msg) {

            $('#display_contenu').html(msg);
            $('#loading').hide();
            tpls_code_mirror(extension);
            $('#display_contenu').fadeIn('fast');
        }
    });
    return false;
}

//Restore
function tpls_restore(path_file) {
    $('#display_contenu').hide();
    $.ajax({
        type: "POST",
        url: "./admin/tplsets/jquery.php",
        // XoopsSecurity::check() requires the request token; read it from
        // the editor form, which now renders it (review catch - without it
        // the handler refused the restore before touching the backup).
        data: {
            op: "tpls_restore",
            path_file: path_file,
            XOOPS_TOKEN_REQUEST: $('form[name="back"] input[name="XOOPS_TOKEN_REQUEST"]').val()
        },
        success: function (msg) {
            $('#display_message').html(msg);
            $('#display_message').show();
            setTimeout(function () {
                $('#display_message').hide();
                $('#display_form').fadeIn('fast');
            }, 1500);

        }
    });
    return false;
}

//Code mirror
function tpls_code_mirror(extension) {
    if (extension == "css") {
        var editor = CodeMirror.fromTextArea("code_mirror", {
            height: "350px",
            parserfile: "parsecss.js",
            stylesheet: "css/code_mirror/csscolors.css",
            lineNumbers: true,
            textWrapping: false,
            path: "js/code_mirror/"
        });
    } else if (extension == "js") {
        var editor = CodeMirror.fromTextArea("code_mirror", {
            height: "350px",
            parserfile: ["tokenizejavascript.js", "parsejavascript.js"],
            stylesheet: "css/code_mirror/jscolors.css",
            autoMatchParens: true,
            lineNumbers: true,
            textWrapping: false,
            path: "js/code_mirror/"
        });
    } else if (extension == "html") {
        var editor = CodeMirror.fromTextArea("code_mirror", {
            height: "350px",
            parserfile: "parsexml.js",
            stylesheet: "css/code_mirror/xmlcolors.css",
            path: "js/code_mirror/",
            continuousScanning: 500,
            lineNumbers: true
        });
    } else {
        var editor = CodeMirror.fromTextArea("code_mirror", {
            height: "350px",
            parserfile: ["parsexml.js", "parsecss.js", "tokenizejavascript.js", "parsejavascript.js",
                "tokenizephp.js", "parsephp.js",
                "parsephphtmlmixed.js"],
            stylesheet: ["css/code_mirror/xmlcolors.css", "css/code_mirror/jscolors.css", "css/code_mirror/csscolors.css", "css/code_mirror/phpcolors.css"],
            lineNumbers: true,
            textWrapping: false,
            path: "js/code_mirror/",
            continuousScanning: 500
        });
    }
}


