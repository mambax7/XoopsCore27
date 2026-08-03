<?php
/**
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

require_once __DIR__ . '/XoopsFormTabRendererInterface.php';
require_once __DIR__ . '/../../xoopseditor/dhtmltextarea/XoopsDhtmlToolbar.php';
require_once __DIR__ . '/XoopsFormRendererValueEscapeTrait.php';

/**
 * Bootstrap4 style form renderer
 *
 * @category  XoopsForm
 * @package   XoopsFormRendererBootstrap4
 * @author    Tad <tad0616@gmail.com>
 * @author    Richard Griffith <richard@geekwright.com>
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 */
class XoopsFormRendererBootstrap4 implements XoopsFormRendererInterface, XoopsFormTabRendererInterface
{
    use XoopsFormRendererValueEscapeTrait;

    /**
     * Counter giving each rendered tab tray a unique DOM id.
     *
     * @var int
     */
    protected static $tabSeq = 0;
    /**
     * Render support for XoopsFormButton
     *
     * @param XoopsFormButton $element form element
     *
     * @return string rendered form element
     */
    public function renderFormButton(XoopsFormButton $element)
    {
        return '<button type="' . $element->getType() . '"'
            . ' class="btn btn-secondary" name="' . $element->getName() . '"'
            . ' id="' . $element->getName() . '" title="' . $this->escapeElementValue($element->getValue()) . '"'
            . ' value="' . $this->escapeElementValue($element->getValue()) . '"'
            . $element->getExtra() . '>' . $element->getValue() . '</button>';
    }

    /**
     * Render support for XoopsFormButtonTray
     *
     * @param XoopsFormButtonTray $element form element
     *
     * @return string rendered form element
     */
    public function renderFormButtonTray(XoopsFormButtonTray $element)
    {
        $ret = '';
        if ($element->_showDelete) {
            $ret .= '<button type="submit" class="btn btn-danger mr-1" name="delete" id="delete" onclick="this.form.elements.op.value=\'delete\'">' . _DELETE
            . '</button>';
        }
        $ret .= '<button class="btn btn-danger mr-1" onClick="history.go(-1);return true;">'
            . _CANCEL . '</button>'
            . '<button type="reset" class="btn btn-warning mr-1" name="reset" id="reset">' . _RESET . '</button>'
            . '<button type="' . $element->getType() . '" class="btn btn-success" name="' . $element->getName()
            . '"  id="' . $element->getName() . '" ' . $element->getExtra()
            . '>' . $element->getValue() . '</button>';

        return $ret;
    }

    /**
     * Render support for XoopsFormCheckBox
     *
     * @param XoopsFormCheckBox $element form element
     *
     * @return string rendered form element
     */
    public function renderFormCheckBox(XoopsFormCheckBox $element)
    {
        $elementName = $element->getName();
        $elementId = $elementName;
        $elementOptions = $element->getOptions();
        if (count($elementOptions) > 1 && substr($elementName, -2, 2) !== '[]') {
            $elementName .= '[]';
            $element->setName($elementName);
        }

        switch ((int) ($element->columns)) {
            case 0:
                return $this->renderCheckedInline($element, 'checkbox', $elementId, $elementName);
            case 1:
                return $this->renderCheckedOneColumn($element, 'checkbox', $elementId, $elementName);
            default:
                return $this->renderCheckedColumnar($element, 'checkbox', $elementId, $elementName);
        }
    }

    /**
     * Render a inline checkbox or radio element
     *
     * @param XoopsFormCheckBox|XoopsFormRadio $element element being rendered
     * @param string                           $type    'checkbox' or 'radio;
     * @param string                           $elementId   input 'id' attribute of element
     * @param string                           $elementName input 'name' attribute of element
     * @return string
     */
    protected function renderCheckedInline($element, $type, $elementId, $elementName)
    {
        $class = $type . '-inline';
        $ret = '';

        $idSuffix = 0;
        $elementValue = $element->getValue();
        $elementOptions = $element->getOptions();
        foreach ($elementOptions as $value => $name) {
            ++$idSuffix;

            $ret .= '<div class="form-check form-check-inline  m-2">';
            $ret .= "<input class='form-check-input' type='" . $type . "' name='{$elementName}' id='{$elementId}{$idSuffix}' title='"
                . htmlspecialchars(strip_tags($name), ENT_QUOTES | ENT_HTML5) . "' value='"
                . htmlspecialchars($value, ENT_QUOTES | ENT_HTML5) . "'";

            if (is_array($elementValue) ? in_array($value, $elementValue) : $value == $elementValue) {
                $ret .= ' checked';
            }
            $ret .= $element->getExtra() . '>';
            $ret .= '<label class="form-check-label" for="' . $elementId . $idSuffix . '">' . $name . $element->getDelimeter() . '</label>';
            $ret .= '</div>';
        }

        return $ret;
    }

    /**
     * Render a single column checkbox or radio element
     *
     * @param XoopsFormCheckBox|XoopsFormRadio $element element being rendered
     * @param string                           $type    'checkbox' or 'radio;
     * @param string                           $elementId   input 'id' attribute of element
     * @param string                           $elementName input 'name' attribute of element
     * @return string
     */
    protected function renderCheckedOneColumn($element, $type, $elementId, $elementName)
    {
        $class = $type;
        $ret = '';

        $idSuffix = 0;
        $elementValue = $element->getValue();
        $elementOptions = $element->getOptions();
        foreach ($elementOptions as $value => $name) {
            ++$idSuffix;
            $ret .= '<div class="' . $class . '">';
            $ret .= '<label>';
            $ret .= "<input type='" . $type . "' name='{$elementName}' id='{$elementId}{$idSuffix}' title='"
                . htmlspecialchars(strip_tags($name), ENT_QUOTES | ENT_HTML5) . "' value='"
                . htmlspecialchars($value, ENT_QUOTES | ENT_HTML5) . "'";

            if (is_array($elementValue) ? in_array($value, $elementValue) : $value == $elementValue) {
                $ret .= ' checked';
            }
            $ret .= $element->getExtra() . '>' . $name . $element->getDelimeter();
            $ret .= '</label>';
            $ret .= '</div>';
        }

        return $ret;
    }

    /**
     * Render a multicolumn checkbox or radio element
     *
     * @param XoopsFormCheckBox|XoopsFormRadio $element element being rendered
     * @param string                           $type    'checkbox' or 'radio;
     * @param string                           $elementId   input 'id' attribute of element
     * @param string                           $elementName input 'name' attribute of element
     * @return string
     */
    protected function renderCheckedColumnar($element, $type, $elementId, $elementName)
    {
        $class = $type;
        $ret = '';

        $idSuffix = 0;
        $elementValue = $element->getValue();
        $elementOptions = $element->getOptions();
        foreach ($elementOptions as $value => $name) {
            ++$idSuffix;

            $ret .= '<div class="form-check m-2">';
            $ret .= "<input class='form-check-input' type='" . $type . "' name='{$elementName}' id='{$elementId}{$idSuffix}' title='"
                . htmlspecialchars(strip_tags($name), ENT_QUOTES | ENT_HTML5) . "' value='"
                . htmlspecialchars($value, ENT_QUOTES | ENT_HTML5) . "'";

            if (is_array($elementValue) ? in_array($value, $elementValue) : $value == $elementValue) {
                $ret .= ' checked';
            }
            $ret .= $element->getExtra() . '>';
            $ret .= '<label class="form-check-label" for="' . $elementId . $idSuffix . '">' . $name . $element->getDelimeter() . '</label>';
            $ret .= '</div>';
        }

        return $ret;
    }
    /**
     * Render support for XoopsFormColorPicker
     *
     * @param XoopsFormColorPicker $element form element
     *
     * @return string rendered form element
     */
    public function renderFormColorPicker(XoopsFormColorPicker $element)
    {
        if (isset($GLOBALS['xoTheme'])) {
            $GLOBALS['xoTheme']->addScript('include/spectrum.js');
            $GLOBALS['xoTheme']->addStylesheet('include/spectrum.css');
        } else {
            echo '<script type="text/javascript" src="' . XOOPS_URL . '/include/spectrum.js"></script>';
            echo '<link rel="stylesheet" type="text/css" href="' . XOOPS_URL . '/include/spectrum.css">';
        }
        return '<input class="form-control" style="width: 25%;" type="color" name="' . $element->getName()
            . "' title='" . $element->getTitle() . "' id='" . $element->getName()
            . '" size="7" maxlength="7" value="' . $this->escapeElementValue($element->getValue()) . '"' . $element->getExtra() . '>';
    }

    /**
     * Render support for XoopsFormDhtmlTextArea
     *
     * @param XoopsFormDhtmlTextArea $element form element
     *
     * @return string rendered form element
     */
    public function renderFormDhtmlTextArea(XoopsFormDhtmlTextArea $element)
    {
        xoops_loadLanguage('formdhtmltextarea');
        $ret = '';
        // toolbar: xoopscode buttons, typography, check-length — shared across all renderers
        $toolbar = new \XoopsDhtmlToolbar();
        $ret .= $toolbar->render($element) . "<br>\n";
        // the textarea box
        $ret .= "<textarea class='form-control' id='" . $element->getName() . "' name='" . $element->getName()
            . "' title='" . $element->getTitle() . "' onselect=\"xoopsSavePosition('" . $element->getName()
            . "');\" onclick=\"xoopsSavePosition('" . $element->getName()
            . "');\" onkeyup=\"xoopsSavePosition('" . $element->getName() . "');\" cols='"
            . $element->getCols() . "' rows='" . $element->getRows() . "'" . $element->getExtra()
            . '>' . $this->escapeElementValue($element->getValue()) . "</textarea>\n";

        if (empty($element->skipPreview)) {
            if (empty($GLOBALS['xoTheme'])) {
                $element->js .= implode('', file(XOOPS_ROOT_PATH . '/class/textsanitizer/image/image.js'));
            } else {
                $GLOBALS['xoTheme']->addScript(
                    '/class/textsanitizer/image/image.js',
                    ['type' => 'text/javascript'],
                );
            }
            $button = "<button type='button' class='btn btn-primary' onclick=\"form_instantPreview('" . XOOPS_URL
                . "', '" . $element->getName() . "','" . XOOPS_URL . "/images', " . (int) $element->doHtml . ", '"
                . $GLOBALS['xoopsSecurity']->createToken() . "')\" title='" . _PREVIEW . "'>" . _PREVIEW . "</button>";

            $ret .= '<br>' . "<div id='" . $element->getName() . "_hidden' style='display: block;'> "
                . '   <fieldset>' . '       <legend>' . $button . '</legend>'
                . "       <div id='" . $element->getName() . "_hidden_data'>" . _XOOPS_FORM_PREVIEW_CONTENT
                . '</div>' . '   </fieldset>' . '</div>';
        }
        // Load javascript
        $javascript_file = XOOPS_URL . '/include/formdhtmltextarea.js';
        $javascript_file_element = 'include_formdhtmltextarea_js';
        $javascript = ($element->js ? '<script type="text/javascript">' . $element->js . '</script>' : '');
        $javascript .= <<<EOJS
<script>
    var el = document.getElementById('{$javascript_file_element}');
    if (el === null) {
        var xformtag = document.createElement('script');
        xformtag.id = '{$javascript_file_element}';
        xformtag.type = 'text/javascript';
        xformtag.src = '{$javascript_file}';
        document.body.appendChild(xformtag);
    }
</script>
EOJS;

        return $javascript . $ret;
    }

    /**
     * Render xoopscode buttons for editor, include calling text sanitizer extensions
     *
     * Thin delegate to the shared {@see XoopsDhtmlToolbar}. Kept (rather than removed) because
     * this method is `protected`, not part of {@see XoopsFormRendererInterface}, and a third-party
     * subclass of this renderer may still call or override it.
     *
     * @param XoopsFormDhtmlTextArea $element form element
     *
     * @return string rendered buttons for xoopscode assistance
     */
    protected function renderFormDhtmlTAXoopsCode(XoopsFormDhtmlTextArea $element)
    {
        return (new \XoopsDhtmlToolbar())->renderCodeButtons($element);
    }

    /**
     * Render typography controls for editor (font, size, color)
     *
     * Thin delegate to the shared {@see XoopsDhtmlToolbar}. Kept (rather than removed) because
     * this method is `protected`, not part of {@see XoopsFormRendererInterface}, and a third-party
     * subclass of this renderer may still call or override it.
     *
     * @param XoopsFormDhtmlTextArea $element form element
     *
     * @return string rendered typography controls
     */
    protected function renderFormDhtmlTATypography(XoopsFormDhtmlTextArea $element)
    {
        $toolbar = new \XoopsDhtmlToolbar();

        return $toolbar->renderTypography($element) . $toolbar->renderCheckLength($element);
    }

    /**
     * Render support for XoopsFormElementTray
     *
     * @param XoopsFormElementTray $element form element
     *
     * @return string rendered form element
     */
    public function renderFormElementTray(XoopsFormElementTray $element)
    {
        $count = 0;
        $inline = (\XoopsFormElementTray::ORIENTATION_VERTICAL === $element->getOrientation());
        $ret = '';
        foreach ($element->getElements() as $ele) {
            if ($count > 0 && !$inline) {
                $ret .= $element->getDelimeter();
            }
            if ($inline) {
                $ret .= '<span class="form-inline">';
            }
            if ($ele->getCaption() != '') {
                $ret .= '<label for="' . $ele->getName() . '" class="form-label text-sm-right">'
                    . $ele->getCaption()
                    . ($ele->isRequired() ? '<span class="caption-required">*</span>' : '')
                    . '</label>&nbsp;';

            }
            $ret .= $ele->render() . NWLINE;
            if ($inline) {
                $ret .= '</span>';
            }
            if (!$ele->isHidden()) {
                ++$count;
            }
        }

        return $ret;
    }

    /**
     * Render support for XoopsFormFile
     *
     * @param XoopsFormFile $element form element
     *
     * @return string rendered form element
     */
    public function renderFormFile(XoopsFormFile $element)
    {
        return '<input type="hidden" name="MAX_FILE_SIZE" value="' . $element->getMaxFileSize() . '">'
            . '<input type="file" class="form-control"  name="' . $element->getName()
        . '" id="' . $element->getName()
        . '" title="' . $element->getTitle() . '" ' . $element->getExtra() . '>'
            . '<input type="hidden" name="xoops_upload_file[]" id="xoops_upload_file[]" value="'
            . $element->getName() . '">';
    }

    /**
     * Render support for XoopsFormLabel
     *
     * @param XoopsFormLabel $element form element
     *
     * @return string rendered form element
     */
    public function renderFormLabel(XoopsFormLabel $element)
    {
        return '<label class="col-form-label" id="' . $element->getName() . '">' . $element->getValue();
    }

    /**
     * Render support for XoopsFormPassword
     *
     * @param XoopsFormPassword $element form element
     *
     * @return string rendered form element
     */
    public function renderFormPassword(XoopsFormPassword $element)
    {
        return '<input class="form-control" type="password" name="'
            . $element->getName() . '" id="' . $element->getName() . '" size="' . $element->getSize()
            . '" maxlength="' . $element->getMaxlength() . '" value="' . $this->escapeElementValue($element->getValue()) . '"'
            . $element->getExtra() . ' ' . ($element->autoComplete ? '' : 'autocomplete="off" ') . '/>';
    }

    /**
     * Render support for XoopsFormRadio
     *
     * @param XoopsFormRadio $element form element
     *
     * @return string rendered form element
     */
    public function renderFormRadio(XoopsFormRadio $element)
    {

        $elementName = $element->getName();
        $elementId = $elementName;

        switch ((int) ($element->columns)) {
            case 0:
                return $this->renderCheckedInline($element, 'radio', $elementId, $elementName);
            case 1:
                return $this->renderCheckedOneColumn($element, 'radio', $elementId, $elementName);
            default:
                return $this->renderCheckedColumnar($element, 'radio', $elementId, $elementName);
        }
    }

    /**
     * Render support for XoopsFormSelect
     *
     * @param XoopsFormSelect $element form element
     *
     * @return string rendered form element
     */
    public function renderFormSelect(XoopsFormSelect $element)
    {
        $ele_name    = $element->getName();
        $ele_title   = $element->getTitle();
        $ele_value   = $element->getValue();
        $ele_options = $element->getOptions();
        $ret = '<select class="form-control" size="'
            . $element->getSize() . '"' . $element->getExtra();
        if ($element->isMultiple() != false) {
            $ret .= ' name="' . $ele_name . '[]" id="' . $ele_name . '" title="' . $ele_title
                . '" multiple="multiple">';
        } else {
            $ret .= ' name="' . $ele_name . '" id="' . $ele_name . '" title="' . $ele_title . '">';
        }
        foreach ($ele_options as $value => $name) {
            $ret .= '<option value="' . htmlspecialchars($value, ENT_QUOTES | ENT_HTML5) . '"';
            if (count($ele_value) > 0 && in_array($value, $ele_value)) {
                $ret .= ' selected';
            }
            $ret .= '>' . $name . '</option>';
        }
        $ret .= '</select>';

        return $ret;
    }

    /**
     * Render support for XoopsFormText
     *
     * @param XoopsFormText $element form element
     *
     * @return string rendered form element
     */
    public function renderFormText(XoopsFormText $element)
    {
        return "<input class='form-control' type='text' name='"
            . $element->getName() . "' title='" . $element->getTitle() . "' id='" . $element->getName()
            . "' size='" . $element->getSize() . "' maxlength='" . $element->getMaxlength()
            . "' value='" . $this->escapeElementValue($element->getValue()) . "'" . $element->getExtra() . '>';
    }

    /**
     * Render support for XoopsFormTextArea
     *
     * @param XoopsFormTextArea $element form element
     *
     * @return string rendered form element
     */
    public function renderFormTextArea(XoopsFormTextArea $element)
    {
        return "<textarea class='form-control' name='"
            . $element->getName() . "' id='" . $element->getName() . "'  title='" . $element->getTitle()
            . "' rows='" . $element->getRows() . "' cols='" . $element->getCols() . "'"
            . $element->getExtra() . '>' . $this->escapeElementValue($element->getValue()) . '</textarea>';
    }

    /**
     * Render support for XoopsFormTextDateSelect
     *
     * @param XoopsFormTextDateSelect $element form element
     *
     * @return string rendered form element
     */
    public function renderFormTextDateSelect(XoopsFormTextDateSelect $element)
    {
        static $included = false;
        if (file_exists(XOOPS_ROOT_PATH . '/language/' . $GLOBALS['xoopsConfig']['language'] . '/calendar.php')) {
            include_once XOOPS_ROOT_PATH . '/language/' . $GLOBALS['xoopsConfig']['language'] . '/calendar.php';
        } else {
            include_once XOOPS_ROOT_PATH . '/language/english/calendar.php';
        }

        $ele_name  = $element->getName();
        $ele_value = $element->getValue(false);
        if (is_string($ele_value)) {
            $display_value = $ele_value;
            $ele_value     = time();
        } elseif ($ele_value === 0) {
            // Blank date: show empty field, use current time for calendar popup seed
            $display_value = '';
            $ele_value     = time();
        } else {
            $display_value = date(_SHORTDATESTRING, $ele_value);
        }

        $jstime = formatTimestamp($ele_value, 'm/d/Y');
        if (isset($GLOBALS['xoTheme']) && is_object($GLOBALS['xoTheme'])) {
            $GLOBALS['xoTheme']->addScript('include/calendar.js');
            $GLOBALS['xoTheme']->addStylesheet('include/calendar-blue.css');
            if (!$included) {
                $included = true;
                $GLOBALS['xoTheme']->addScript('', '', '
                    var calendar = null;

                    function selected(cal, date)
                    {
                    cal.sel.value = date;
                    }

                    function closeHandler(cal)
                    {
                    cal.hide();
                    Calendar.removeEvent(document, "mousedown", checkCalendar);
                    }

                    function checkCalendar(ev)
                    {
                    var el = Calendar.is_ie ? Calendar.getElement(ev) : Calendar.getTargetElement(ev);
                    for (; el != null; el = el.parentNode)
                    if (el == calendar.element || el.tagName == "A") break;
                    if (el == null) {
                    calendar.callCloseHandler(); Calendar.stopEvent(ev);
                    }
                    }
                    function showCalendar(id)
                    {
                    var el = xoopsGetElementById(id);
                    if (calendar != null) {
                    calendar.hide();
                    } else {
                    var cal = new Calendar(true, "' . $jstime . '", selected, closeHandler);
                    calendar = cal;
                    cal.setRange(1900, 2100);
                    calendar.create();
                    }
                    calendar.sel = el;
                    calendar.parseDate(el.value);
                    calendar.showAtElement(el);
                    Calendar.addEvent(document, "mousedown", checkCalendar);

                    return false;
                    }

                    Calendar._DN = new Array
                    ("' . _CAL_SUNDAY . '",
                    "' . _CAL_MONDAY . '",
                    "' . _CAL_TUESDAY . '",
                    "' . _CAL_WEDNESDAY . '",
                    "' . _CAL_THURSDAY . '",
                    "' . _CAL_FRIDAY . '",
                    "' . _CAL_SATURDAY . '",
                    "' . _CAL_SUNDAY . '");
                    Calendar._MN = new Array
                    ("' . _CAL_JANUARY . '",
                    "' . _CAL_FEBRUARY . '",
                    "' . _CAL_MARCH . '",
                    "' . _CAL_APRIL . '",
                    "' . _CAL_MAY . '",
                    "' . _CAL_JUNE . '",
                    "' . _CAL_JULY . '",
                    "' . _CAL_AUGUST . '",
                    "' . _CAL_SEPTEMBER . '",
                    "' . _CAL_OCTOBER . '",
                    "' . _CAL_NOVEMBER . '",
                    "' . _CAL_DECEMBER . '");

                    Calendar._TT = {};
                    Calendar._TT["TOGGLE"] = "' . _CAL_TGL1STD . '";
                    Calendar._TT["PREV_YEAR"] = "' . _CAL_PREVYR . '";
                    Calendar._TT["PREV_MONTH"] = "' . _CAL_PREVMNTH . '";
                    Calendar._TT["GO_TODAY"] = "' . _CAL_GOTODAY . '";
                    Calendar._TT["NEXT_MONTH"] = "' . _CAL_NXTMNTH . '";
                    Calendar._TT["NEXT_YEAR"] = "' . _CAL_NEXTYR . '";
                    Calendar._TT["SEL_DATE"] = "' . _CAL_SELDATE . '";
                    Calendar._TT["DRAG_TO_MOVE"] = "' . _CAL_DRAGMOVE . '";
                    Calendar._TT["PART_TODAY"] = "(' . _CAL_TODAY . ')";
                    Calendar._TT["MON_FIRST"] = "' . _CAL_DISPM1ST . '";
                    Calendar._TT["SUN_FIRST"] = "' . _CAL_DISPS1ST . '";
                    Calendar._TT["CLOSE"] = "' . _CLOSE . '";
                    Calendar._TT["TODAY"] = "' . _CAL_TODAY . '";

                    // date formats
                    Calendar._TT["DEF_DATE_FORMAT"] = "' . _SHORTDATESTRING . '";
                    Calendar._TT["TT_DATE_FORMAT"] = "' . _SHORTDATESTRING . '";

                    Calendar._TT["WK"] = "";
                ');
            }
        }
        return '<div class="input-group">'
            . '<input class="form-control" type="text" name="' . $ele_name . '" id="' . $ele_name
            . '" size="' . $element->getSize() . '" maxlength="' . $element->getMaxlength()
            . '" value="' . $display_value . '"' . $element->getExtra() . '>'
            . '<div class="input-group-append"><button class="btn btn-secondary" type="button"'
            . ' onclick="return showCalendar(\'' . $ele_name . '\');">'
            . '<i class="fa-solid fa-calendar" aria-hidden="true"></i></button>'
            . '</div>'
            . '</div>';
    }

    /**
     * Render support for XoopsThemeForm
     *
     * @param XoopsThemeForm $form form to render
     *
     * @return string rendered form
     */
    public function renderThemeForm(XoopsThemeForm $form)
    {
        $ele_name = $form->getName();

        $ret = '<div>';
        $ret .= '<form name="' . $ele_name . '" id="' . $ele_name . '" action="'
            . $form->getAction() . '" method="' . $form->getMethod()
            . '" onsubmit="return xoopsFormValidate_' . $ele_name . '();"' . $form->getExtra() . '>'
            . '<h3>' . $form->getTitle() . '</h3>';
        $hidden   = '';

        foreach ($form->getElements() as $element) {
            if (!is_object($element)) { // see $form->addBreak()
                $ret .= $element;
                continue;
            }
            if ($element->isHidden()) {
                $hidden .= $element->render();
                continue;
            }

            if ($element instanceof XoopsFormTabTray) {
                // tabs own their layout; render full width rather than in a label/value row
                $ret .= '<div class="form-group">' . $element->render() . '</div>';
                continue;
            }

            $ret .= '<div class="form-group row">';
            if (($caption = $element->getCaption()) != '') {
                $ret .= '<label for="' . $element->getName() . '" class="col-xs-12 col-sm-2 col-form-label text-sm-right">'
                    . $element->getCaption()
                    . ($element->isRequired() ? '<span class="xo-caption-required">*</span>' : '')
                    . '</label>';
            } else {
                $ret .= '<div class="col-xs-12 col-sm-2"> </div>';
            }
            $ret .= '<div class="col-xs-12 col-sm-10">';
            $ret .= (string) $element->render();
            if (($desc = $element->getDescription()) != '') {
                $ret .= '<p class="form-text text-muted">' . $desc . '</p>';
            }
            $ret .= '</div>';
            $ret .= '</div>';
        }
        if (count($form->getRequired()) > 0) {
            //  Add caption marker constructed using renderer's formatting
            $ret .= NWLINE . '<div class="col-12 mb-2"> <span class="xo-caption-required">*</span> = ' . _REQUIRED . '</div>' . NWLINE;
        }
        $ret .= $hidden;
        $ret .= '</form></div>';
        $ret .= $form->renderValidationJS(true);

        return $ret;
    }

    /**
     * Support for themed addBreak
     *
     * @param XoopsThemeForm $form
     * @param string         $extra pre-rendered content for break row
     * @param string         $class class for row
     *
     * @return void
     */
    public function addThemeFormBreak(XoopsThemeForm $form, $extra, $class)
    {
        $class = ($class != '') ? preg_replace('/[^A-Za-z0-9\s\s_-]/i', '', $class) : '';
        $form->addElement('<div class="col-md-12 ' . $class . '">' . $extra . '</div>');
    }

    /**
     * Render support for XoopsFormTabTray using Bootstrap 4 nav-tabs.
     *
     * @param XoopsFormTabTray $element the tab tray to render
     *
     * @return string rendered HTML
     */
    public function renderFormTabTray(XoopsFormTabTray $element): string
    {
        $base = $element->getName(false);
        $id   = 'xo-tabs-' . ('' !== $base ? preg_replace('/[^A-Za-z0-9]+/', '', $base) . '-' : '') . ++self::$tabSeq;

        $nav     = '<ul class="nav nav-tabs" role="tablist" id="' . $id . '">';
        $content = '<div class="tab-content" id="' . $id . '-content">';
        $hidden  = '';

        foreach ($element->getTabs() as $k => $tab) {
            $tabId      = $id . '-tab-' . $k;
            $paneId     = $id . '-pane-' . $k;
            $isActive   = ($element->getActiveTab() === $k);
            $navActive  = $isActive ? ' active' : '';
            $paneActive = $isActive ? ' show active' : '';
            $selected   = $isActive ? 'true' : 'false';

            $nav .= '<li class="nav-item" role="presentation">'
                . '<a class="nav-link' . $navActive . '" id="' . $tabId . '" data-toggle="tab" href="#'
                . $paneId . '" role="tab" aria-controls="' . $paneId . '" aria-selected="' . $selected . '"'
                . ' title="' . htmlspecialchars((string) $tab['title'], ENT_QUOTES | ENT_HTML5) . '">'
                . htmlspecialchars((string) $tab['title'], ENT_QUOTES | ENT_HTML5) . '</a></li>';

            $rows = '';
            foreach ($tab['elements'] as $ele) {
                if ($ele->isHidden()) {
                    $hidden .= $ele->render();
                    continue;
                }
                $rows .= $this->renderTabPaneRow($ele);
            }

            $content .= '<div class="tab-pane fade' . $paneActive . '" id="' . $paneId . '" role="tabpanel" aria-labelledby="'
                . $tabId . '">' . $rows . '</div>';
        }

        $nav     .= '</ul>';
        $content .= '</div>';

        return $nav . $content . $hidden;
    }

    /**
     * Render a single element row inside a tab pane, matching the Bootstrap 4
     * row layout used by {@see renderThemeForm()}.
     *
     * @param XoopsFormElement $element element to render
     *
     * @return string
     */
    protected function renderTabPaneRow(XoopsFormElement $element)
    {
        $ret = '<div class="form-group row">';
        if (($caption = $element->getCaption()) != '') {
            $ret .= '<label for="' . $element->getName() . '" class="col-12 col-sm-2 col-form-label text-sm-right">'
                . $caption
                . ($element->isRequired() ? '<span class="xo-caption-required">*</span>' : '')
                . '</label>';
        } else {
            $ret .= '<div class="col-12 col-sm-2"> </div>';
        }
        $ret .= '<div class="col-12 col-sm-10">';
        $ret .= (string) $element->render();
        if (($desc = $element->getDescription()) != '') {
            $ret .= '<small class="form-text text-muted">' . $desc . '</small>';
        }
        $ret .= '</div></div>';

        return $ret;
    }
}
