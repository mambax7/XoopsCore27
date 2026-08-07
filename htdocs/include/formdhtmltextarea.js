function xoopsCodeUrl(id, enterUrlPhrase, enterWebsitePhrase) {
    if (enterUrlPhrase == null) {
        enterUrlPhrase = "Enter the URL of the link you want to add:";
    }
    var text = prompt(enterUrlPhrase, "");
    var domobj = xoopsGetElementById(id);
    if (text != null && text != "") {
        var selection = xoopsGetSelect(id);
        if (selection.length > 0) {
            var text2 = selection;
        } else {
            var text2 = prompt(enterWebsitePhrase, "");
        }
        if (text2 != null) {
            if (text2 == "") {
                var result = "[url=" + text + "]" + text + "[/url]";
            } else {
                var pos = text2.indexOf(unescape('%00'));
                if (0 < pos) {
                    text2 = text2.substr(0, pos);
                }
                var result = "[url=" + text + "]" + text2 + "[/url]";
            }
            xoopsInsertText(domobj, result);
        }
    }
    domobj.focus();
}

function xoopsCodeImg(id, enterImgUrlPhrase, enterImgPosPhrase, imgPosRorLPhrase, errorImgPosPhrase, enterImgWidthPhrase) {
    if (enterImgUrlPhrase == null) {
        enterImgUrlPhrase = "Enter the URL of the image you want to add:";
    }
    var selection = xoopsGetSelect(id);
    if (selection.length > 0) {
        var text = selection;
    } else {
        var text = prompt(enterImgUrlPhrase, "");
    }
    var domobj = xoopsGetElementById(id);
    if (text != null && text != "") {
        if (enterImgPosPhrase == null) {
            enterImgPosPhrase = "Now, enter the position of the image.";
        }
        if (imgPosRorLPhrase == null) {
            imgPosRorLPhrase = "'R' or 'r' for right, 'L' or 'l' for left, or leave it blank.";
        }
        if (errorImgPosPhrase == null) {
            errorImgPosPhrase = "ERROR! Enter the position of the image:";
        }
        var text2 = prompt(enterImgPosPhrase + "\n" + imgPosRorLPhrase, "");
        while (( text2 != "" ) && ( text2 != "r" ) && ( text2 != "R" ) && ( text2 != "l" ) && ( text2 != "L" ) && ( text2 != null )) {
            text2 = prompt(errorImgPosPhrase + "\n" + imgPosRorLPhrase, "");
        }
        if (text2 == "l" || text2 == "L") {
            text2 = " align=left";
        } else if (text2 == "r" || text2 == "R") {
            text2 = " align=right";
        } else {
            text2 = "";
        }

        var text3 = (prompt(enterImgWidthPhrase, "") || "").trim();
        if (/^[1-9]\d*$/.test(text3)) {
            text3 = " width=" + text3;
        } else {
            text3 = "";
        }

        var result = "[img" + text2 + text3 + "]" + text + "[/img]";
        xoopsInsertText(domobj, result);
    }
    domobj.focus();
}

function xoopsCodeEmail(id, enterEmailPhrase) {
    if (enterEmailPhrase == null) {
        enterEmailPhrase = "Enter the email address you want to add:";
    }
    var selection = xoopsGetSelect(id);
    if (selection.length > 0) {
        var text = selection;
    } else {
        var text = prompt(enterEmailPhrase, "");
    }
    var domobj = xoopsGetElementById(id);
    if (text != null && text != "") {
        var result = "[email]" + text + "[/email]";
        xoopsInsertText(domobj, result);
    }
    domobj.focus();
}

function xoopsCodeQuote(id, enterQuotePhrase) {
    if (enterQuotePhrase == null) {
        enterQuotePhrase = "Enter the text that you want to be quoted:";
    }
    var selection = xoopsGetSelect(id);
    if (selection.length > 0) {
        var text = selection;
    } else {
        var text = prompt(enterQuotePhrase, "");
    }
    var domobj = xoopsGetElementById(id);
    if (text != null && text != "") {
        var pos = text.indexOf(unescape('%00'));
        if (0 < pos) {
            text = text.substr(0, pos);
        }
        var result = "[quote]" + text + "[/quote]";
        xoopsInsertText(domobj, result);
    }
    domobj.focus();
}

function xoopsCodeCode(id, enterCodePhrase) {
    if (enterCodePhrase == null) {
        enterCodePhrase = "Enter the codes that you want to add.";
    }
    var selection = xoopsGetSelect(id);
    if (selection.length > 0) {
        var text = selection;
    } else {
        var text = prompt(enterCodePhrase, "");
    }
    var domobj = xoopsGetElementById(id);
    if (text != null && text != "") {
        var result = "[code]" + text + "[/code]";
        xoopsInsertText(domobj, result);
    }
    domobj.focus();
}

// Legacy helper from an older toolbar layout that had a separate "add text" box alongside
// the Font/Colour/Size selects. No renderer has emitted an "<id>Addtext" element or an
// element with the hiddentext id for a long time, and nothing in core or any bundled
// extension calls this function, so every path through it dereferenced null. It is kept
// only because it is a global that a third-party module could still call; it now returns
// harmlessly instead of throwing a TypeError.
function xoopsCodeText(id, hiddentext, enterTextboxPhrase) {
    var textareaDom = xoopsGetElementById(id);
    var textDom = xoopsGetElementById(id + "Addtext");
    var fontDom = xoopsGetElementById(id + "Font");
    var colorDom = xoopsGetElementById(id + "Color");
    var sizeDom = xoopsGetElementById(id + "Size");
    var hiddenDom = xoopsGetElementById(hiddentext);
    if (!textareaDom || !textDom || !fontDom || !colorDom || !sizeDom || !hiddenDom) {
        return;
    }
    var xoopsHiddenTextDomStyle = hiddenDom.style;
    var selection = xoopsGetSelect(id);
    if (selection.length > 0) {
        var textDomValue = selection;
    } else {
        var textDomValue = textDom.value;
    }
    var fontDomValue = fontDom.options[fontDom.options.selectedIndex].value;
    var colorDomValue = colorDom.options[colorDom.options.selectedIndex].value;
    var sizeDomValue = sizeDom.options[sizeDom.options.selectedIndex].value;
    if (textDomValue == "") {
        if (enterTextboxPhrase == null) {
            enterTextboxPhrase = "Please input text into the textbox.";
        }
        alert(enterTextboxPhrase);
        textDom.focus();
    } else {
        if (fontDomValue != "FONT") {
            textDomValue = "[font=" + fontDomValue + "]" + textDomValue + "[/font]";
            fontDom.options[0].selected = true;
        }
        if (colorDomValue != "COLOR") {
            textDomValue = "[color=" + colorDomValue + "]" + textDomValue + "[/color]";
            colorDom.options[0].selected = true;
        }
        if (sizeDomValue != "SIZE") {
            textDomValue = "[size=" + sizeDomValue + "]" + textDomValue + "[/size]";
            sizeDom.options[0].selected = true;
        }
        if (xoopsHiddenTextDomStyle.fontWeight == "bold" || xoopsHiddenTextDomStyle.fontWeight == "700") {
            textDomValue = "[b]" + textDomValue + "[/b]";
            xoopsHiddenTextDomStyle.fontWeight = "normal";
        }
        if (xoopsHiddenTextDomStyle.fontStyle == "italic") {
            textDomValue = "[i]" + textDomValue + "[/i]";
            xoopsHiddenTextDomStyle.fontStyle = "normal";
        }
        if (xoopsHiddenTextDomStyle.textDecoration == "underline") {
            textDomValue = "[u]" + textDomValue + "[/u]";
            xoopsHiddenTextDomStyle.textDecoration = "none";
        }
        if (xoopsHiddenTextDomStyle.textDecoration == "line-through") {
            textDomValue = "[d]" + textDomValue + "[/d]";
            xoopsHiddenTextDomStyle.textDecoration = "none";
        }
        if (xoopsHiddenTextDomStyle.textalign == "center") {
            textDomValue = "[center]" + textDomValue + "[/center]";
            xoopsHiddenTextDomStyle.textalign = "none";
        }
        if (xoopsHiddenTextDomStyle.textalign == "left") {
            textDomValue = "[left]" + textDomValue + "[/left]";
            xoopsHiddenTextDomStyle.textalign = "none";
        }
        if (xoopsHiddenTextDomStyle.textalign == "right") {
            textDomValue = "[right]" + textDomValue + "[/right]";
            xoopsHiddenTextDomStyle.textalign = "none";
        }

        xoopsInsertText(textareaDom, textDomValue);
        textDom.value = "";
        xoopsHiddenTextDomStyle.color = "#000000";
        xoopsHiddenTextDomStyle.fontFamily = "";
        xoopsHiddenTextDomStyle.fontSize = "12px";
        xoopsHiddenTextDomStyle.visibility = "hidden";
        textareaDom.focus();
    }
}

function xoopsGetSelect(id) {
    if (window.getSelection) {
        ele = document.getElementById(id);
        var selection = ele.value.substring(
            ele.selectionStart, ele.selectionEnd
        );
    } else if (document.getSelection) {
        var selection = document.getSelection();
    } else if (document.selection) {
        var selection = document.selection.createRange().text;
    } else {
        var selection = null;
    }
    return selection;
}


function xoopsSetElementAttribute(key, val, id, eid) {
    // xoopsGetSelect() returns null on browsers with no selection API (its final else
    // branch), so normalise before use: otherwise text.length throws and the tag pair
    // below would contain the literal string "null".
    var text = xoopsGetSelect(id);
    if (text === null || text === undefined) {
        text = "";
    }
    // With no selection this used to call setVisible("xoopsHiddenText") and then eval a
    // setElementSize/Font/Color() helper. That path was dead twice over: no renderer emits
    // an element with that id (so setVisible() threw a TypeError on null), and no
    // setElement* function has ever existed in this file. Size/Font/Colour therefore did
    // nothing at all unless text was selected. Insert an empty tag pair instead, which is
    // the behaviour every other editor has and what the user is asking for by clicking.
    var domobj = xoopsGetElementById(id);
    xoopsInsertText(domobj, "[" + key + "=" + val + "]" + text + "[/" + key + "]");
    domobj.focus();
}

function makeBold(id) {
    var eleStyle = xoopsGetElementById(id).style;
    if (eleStyle.fontWeight != "bold" && eleStyle.fontWeight != "700") {
        eleStyle.fontWeight = "bold";
    } else {
        eleStyle.fontWeight = "normal";
    }
}

function makeItalic(id) {
    var eleStyle = xoopsGetElementById(id).style;
    if (eleStyle.fontStyle != "italic") {
        eleStyle.fontStyle = "italic";
    } else {
        eleStyle.fontStyle = "normal";
    }
}

function makeUnderline(id) {
    var eleStyle = xoopsGetElementById(id).style;
    if (eleStyle.textDecoration != "underline") {
        eleStyle.textDecoration = "underline";
    } else {
        eleStyle.textDecoration = "none";
    }
}

function makeLineThrough(id) {
    var eleStyle = xoopsGetElementById(id).style;
    if (eleStyle.textDecoration != "line-through") {
        eleStyle.textDecoration = "line-through";
    } else {
        eleStyle.textDecoration = "none";
    }
}

// Explicit dispatch for the legacy live-preview helpers. Replaces an eval() of a
// caller-supplied function name: only these four are ever valid targets, so a lookup is
// both safer and clearer than evaluating a string.
var xoopsStylePreviewFuncs = {
    makeBold: makeBold,
    makeItalic: makeItalic,
    makeUnderline: makeUnderline,
    makeLineThrough: makeLineThrough
};

function xoopsMakeStyle(id, eid, val, func) {
    // See xoopsSetElementAttribute(): xoopsGetSelect() can return null.
    var text = xoopsGetSelect(id);
    if (text === null || text === undefined) {
        text = "";
    }
    // The no-selection branch toggles a hidden live-preview element. No current renderer
    // emits one, so setVisible() dereferenced null and threw — B/I/U/S did nothing unless
    // text was selected. Take it only when the element genuinely exists (kept for any
    // custom renderer that still emits it); otherwise fall through and insert an empty
    // tag pair so the click does something useful.
    if (text.length <= 0 && func.length > 0 && eid.length > 0
        && xoopsGetElementById(eid) && xoopsStylePreviewFuncs[func]) {
        setVisible(eid);
        xoopsStylePreviewFuncs[func](eid);
        return;
    }
    var domobj = xoopsGetElementById(id);
    xoopsInsertText(domobj, "[" + val + "]" + text + "[/" + val + "]");
    domobj.focus();
}

function xoopsMakeBold(eid, id) {
    xoopsMakeStyle(id, eid, "b", "makeBold");
}

function xoopsMakeItalic(eid, id) {
    xoopsMakeStyle(id, eid, "i", "makeItalic");
}

function xoopsMakeUnderline(eid, id) {
    xoopsMakeStyle(id, eid, "u", "makeUnderline");
}

function xoopsMakeLineThrough(eid, id) {
    xoopsMakeStyle(id, eid, "d", "makeLineThrough");
}

function xoopsMakeCenter(eid, id) {
    xoopsMakeStyle(id, eid, "center", "");
}

function xoopsMakeLeft(eid, id) {
    xoopsMakeStyle(id, eid, "left", "");
}

function xoopsMakeRight(eid, id) {
    xoopsMakeStyle(id, eid, "right", "");
}

// very rough calculation on text length
function XoopsCheckLength(id, maxlength, currentLengthPhrase, maxLengthPhrase) {
    var mb_len_extra = 2;
    var domobj = xoopsGetElementById(id);
    var len = domobj.value.length;

    if (len > 50 * 1024) {
        len_current = " > 50K";
    } else if (len > 30 * 1024) {
        len_current = " 30-50K";
    } else if (len > 10 * 1024) {
        len_current = " 10-30K";
    } else if (len > 5 * 1024) {
        len_current = " 5-10K";
    } else if (len > 1 * 1024) {
        len_current = " 1-5K";
    } else {
        len_current = len;
        for (var n = 0; n < len; n++) {
            if (domobj.value.charAt(n) > '~') {
                len_current += mb_len_extra;
            }
        }

        len_current = len_current + " bytes";
    }

    var string = currentLengthPhrase.replace(/\%s/, len_current);
    if (maxlength > 0) string += ' [' + maxLengthPhrase + maxlength + ']';
    alert(string);
}


function xoopsValidate(subjectId, textareaId, submitId, plzCompletePhrase, msgTooLongPhrase, allowedCharPhrase, currCharPhrase) {
    var maxchars = 65535;
    var subjectDom = xoopsGetElementById(subjectId);
    var textareaDom = xoopsGetElementById(textareaId);
    var submitDom = xoopsGetElementById(submitId);
    if (textareaDom.value == "" || subjectDom.value == "") {
        if (plzCompletePhrase == null) {
            plzCompletePhrase = "Please complete the subject and message fields.";
        }
        alert(plzCompletePhrase);
        return false;
    }
    if (maxchars != 0) {
        if (textareaDom.value.length > maxchars) {
            if (msgTooLongPhrase == null) {
                msgTooLongPhrase = "Your message is too long.";
            }
            if (allowedCharPhrase == null) {
                allowedCharPhrase = "Allowed max chars length: ";
            }
            if (currCharPhrase == null) {
                currCharPhrase = "Current chars length: ";
            }
            alert(msgTooLongPhrase + "\n\n" + allowedCharPhrase + maxchars + "\n" + currCharPhrase + textareaDom.value.length + "");
            textareaDom.focus();
            return false;
        } else {
            submitDom.disabled = true;
            return true;
        }
    } else {
        submitDom.disabled = true;
        return true;
    }
}

// AJAX code for preview
var form_area_id;
var http_request = false;
function makeRequest(area_id, url, arg, method) {
    http_request = false;
    form_area_id = area_id;

    if (window.XMLHttpRequest) { // Mozilla, Safari,...
        http_request = new XMLHttpRequest();
        if (http_request.overrideMimeType) {
            http_request.overrideMimeType('text/xml');
        }
    } else if (window.ActiveXObject) { // IE
        try {
            http_request = new ActiveXObject("Msxml2.XMLHTTP");
        } catch (e) {
            try {
                http_request = new ActiveXObject("Microsoft.XMLHTTP");
            } catch (e) {
            }
        }
    }

    if (!http_request) {
        alert('Giving up :( Cannot create an XMLHTTP instance');
        return false;
    }
    if (!method || method != 'POST') {
        method = 'GET';
    } else {
        method = 'POST';

    }

    http_request.onreadystatechange = alertContents;
    http_request.open(method, url, true);
    if (method == 'POST') {
        //Send the proper header information along with the request
        http_request.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        //http_request.setRequestHeader("Content-length", arg.length);
        //http_request.setRequestHeader("Connection", "close");
    }
    http_request.send(arg);
}

function alertContents() {
    if (http_request.readyState == 4) {
        if (http_request.status == 200) {
            document.getElementById(form_area_id + '_hidden_data').innerHTML = http_request.responseText;
        } else {
            alert(" Server Not Responding ... Please Try later ");
        }
    }
}


function form_checkserver(area_id) {
    if (!xdh_triggered[area_id]) {
        alert(" Ohh.. Server Not Responding ... Please Try later ");
        xoopsGetElementById(area_id + '_preview_button').disabled = false;
        document.getElementById(area_id + '_hidden_data').innerHTML = "";
        xdh_triggered[area_id] = 1;
    }
}

function form_instantPreview(xoopsUrl, area_id, imgurl, doHtml, token) {
    var imgUrl = xoopsUrl + '/images/form';
    var data = encodeURIComponent(xoopsGetElementById(area_id).value);

    var url_request = xoopsUrl + "/include/formdhtmltextarea_preview.php";//?text=" + data;
    var args = "text=" + data;

    if (doHtml) {
        args += '&html=' + doHtml;
        //url_request += '&html=' + doHtml;
    }
    args += '&token=' + token;
    makeRequest(area_id, url_request, args, 'POST');  // - Made ajax Hidden
}
