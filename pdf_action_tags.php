<?php
/*
 * REDCap Customizations - PDF ActionTags
 *
 * Author:
 * Günther A. REZNICZEK, PhD (guenther.rezniczek@rub.de)
 * Ruhr-Universität Bochum
 *
 * Adds support for a number of action tags controlling PDF output.
 *
 * To enable this functionality, include this file and add the following single line of code just before the
 * call to renderPDF() at the bottom of 'redcap_vX.Y.Z/PDF/index.php':
 *
 * $metadata = gr_apply_pdf_actiontags($metadata, $Data);
 *
 * =====
 *
 * Newly added action tags are:
 *
 * @HIDDEN-PDF
 * When present in a field, it will be excluded from the PDF.
 *
 * @PDF-HIDDENDATA[="field_name"]
 * Omits a field from the PDF for 'form with saved data' only. When the name of another field is supplied as parameter,
 * the tagged field will be omitted from 'form with saved data' when actual data is present in the specified field.
 *
 * @PDF-HIDDENNODATA[="field_name"]
 * Omits a field from the PDF for 'form (empty)' or for 'form with saved data' when no actual data is present
 * in the field specified in the parameter.
 *
 * @PDF-NOENUM
 * When present in a field (sql, select, radio), the enumeration of the list members will be suppressed.
 * Instead, the field is “converted” to a text field, i.e. it will be represented with an empty line.
 * When a data value is present, the value will be preserved.
 *
 * @PDF-DATANOENUM
 * Behaves like @PDF-NOENUM, but only applies to 'form with saved data'.
 *
 * @PDF-WHITESPACE="number of lines"
 * This actiontag only applies to 'form (empty)' PDFs. If present, it will add the given number of empty lines
 * to a field's label, pushing down the next field on the page. 'number of lines' must be a positive integer.
 *
 * @PDF-FIELDNOTEEMPTY="text"
 * This actiontag only applies to 'form (empty)' PDFs. When set, it replaces the field note of a field with
 * the text supplied as parameter.
 *
 * @PDF-FIELDNOTEDATA="text"
 * This actiontag only applies to 'form with saved data' PDFs. When set, it replaces the field note of a field with
 * the text suplied as parameter, but only when there is an actual data value present in the field.
 */

/**
 * Extracts the parameter of an action tag
 *
 * @param $misc string String in which to search for an actiontag
 * @param $tag string Actiontag to look for
 * @return bool|string Parameter content or false if there was no parameter
 */
function gr_get_actiontag_param($misc, $tag)
{
    $param = false;
    $pos = strpos($misc, $tag."=");
    if ($pos !== false) {
        $rest = substr($misc, $pos + strlen($tag) + 1);
        $quotechar = substr($rest, 0, 1);
        $end = strpos($rest, $quotechar, 1);
        if ($end !== false) {
            $param_len = strpos($rest, $quotechar, 1) - 1;
            $param = substr($rest, 1, $param_len);
        }
    }
    return $param;
}

/**
 * Applies PDF action tags
 *
 * @param $metadata array The metadata array generated in redcap/PDF/index.php
 * @param $Data array The data array from redcap/PDF/index.php
 * @return array A filtered metadata arry. Use this to replace $metadata just before passing to renderPDF at the bottom of redcap/PDF/index.php
 */
function gr_apply_pdf_actiontags($metadata, &$Data)
{
    // List of element types that support the @PDF-NOENUM actiontag
    $noenum_supported_types = array ( 'sql', 'select', 'radio' );

    // 'form with saved data' or 'form (empty)' ?
    $with_data = !(isset($Data['']) || empty($Data));

    // Get an idea, where data values are present
    $dv = array();
    foreach ($metadata as $attr) {
        $field_data_value = '';
        if ($with_data) {
            // Check specific value
            foreach ($Data as $this_record => $event_data) {
                foreach ($event_data as $this_event_id => $field_data) {
                    $field_data_value = $field_data[$attr['field_name']];
                }
            }
        }
        // Store result; value or false when no value is present
        $dv[$attr['field_name']] = $field_data_value != '' ? $field_data_value : false;
    }

    $filtered_metadata = array();
    foreach ($metadata as $attr) {

        $fieldname = $attr['field_name'];

        // Only process other PDF action tags if @HIDDEN-PDF is _not_ present
        if (strpos($attr['misc'], "@HIDDEN-PDF") === false) {

            $include = true;

            // @PDF-HIDDENNODATA
            if ($include && strpos($attr['misc'], '@PDF-HIDDENNODATA') !== false) {
                // Supplied with parameter?
                if ($targetfield = gr_get_actiontag_param($attr['misc'], '@PDF-HIDDENNODATA')) {
                    if (array_key_exists($targetfield, $dv)) {
                        $include = $dv[$targetfield] !== false;
                    }
                }
                else {
                    $include = $with_data;
                }
            }

            // @PDF-HIDDENDATA
            if ($include && strpos($attr['misc'], '@PDF-HIDDENDATA') !== false) {
                // Supplied with parameter?
                if ($targetfield = gr_get_actiontag_param($attr['misc'], '@PDF-HIDDENDATA')) {
                    if (array_key_exists($targetfield, $dv)) {
                        $include = !($dv[$targetfield] !== false);
                    }
                }
                else {
                    $include = !$with_data;
                }
            }

            // @PDF-WHITESPACE (only applies when there is no data present)
            if ($include && $dv[$fieldname] === false && strpos($attr['misc'], '@PDF-WHITESPACE=') !== false) {
                // get parameter value
                $param = gr_get_actiontag_param($attr['misc'], '@PDF-WHITESPACE');
                if (is_numeric($param)) {
                    $n = max(0, (int)$param); // no negativ values!
                    // insert placeholder data
                    $whitespace = str_repeat("\n", $n);
                    $attr['element_label'] = $attr['element_label'].$whitespace;
                }
            }

            // @PDF-FIELDNOTEEMPTY
            if ($include && !$with_data && strpos($attr['misc'], '@PDF-FIELDNOTEEMPTY=') !== false) {
                // get parameter value
                $note = gr_get_actiontag_param($attr['misc'], '@PDF-FIELDNOTEEMPTY');
                if ($note !== false) {
                    $attr['element_note'] = "$note";
                }
            }

            // @PDF-FIELDNOTEDATA
            if ($include && $with_data && strpos($attr['misc'], '@PDF-FIELDNOTEDATA=') !== false) {
                // get parameter value
                $note = gr_get_actiontag_param($attr['misc'], '@PDF-FIELDNOTEDATA');
                if ($note !== false && $dv[$fieldname] !== false) { // only apply when actual data is present
                    $attr['element_note'] = "$note";
                }
            }

            // @PDF-NOENUM
            if ($include && strpos($attr['misc'], '@PDF-NOENUM') !== false && in_array($attr['element_type'], $noenum_supported_types)) {
                // check if data is present and if so, replace key values with data from the enums
                if ($dv[$fieldname] !== false) {
                    // make the enum 'accessible'
                    $enumvalues = array();
                    $lines = explode("\\n", $attr['element_enum']);
                    foreach ($lines as $l) {
                        $kv = explode(",", $l, 2);
                        $key = trim($kv[0]);
                        $value = trim($kv[1]);
                        $enumvalues[$key] = $value;
                    }
                    // replace data value with text from enum
                    foreach ($Data as $this_record => &$event_data) {
                        foreach ($event_data as $this_event_id => &$field_data) {
                            $keyvalue = $field_data[$attr['field_name']];
                            // if value is not blank
                            if ($keyvalue != '') {
                                $field_data[$attr['field_name']] = $enumvalues[$keyvalue];
                            }
                        }
                    }
                }
                 // Change element type to 'text' so a line is displayed in the PDF if there is no value
                $attr['element_type'] = "text";
            }

            // @PDF-DATANOENUM
            if ($include && strpos($attr['misc'], '@PDF-DATANOENUM') !== false && in_array($attr['element_type'], $noenum_supported_types)) {
                if ($dv[$fieldname] !== false) {
                    // make the enum 'accessible'
                    $enumvalues = array();
                    $lines = explode("\\n", $attr['element_enum']);
                    foreach ($lines as $l) {
                        $kv = explode(",", $l, 2);
                        $key = trim($kv[0]);
                        $value = trim($kv[1]);
                        $enumvalues[$key] = $value;
                    }
                    // replace data value with text from enum
                    foreach ($Data as $this_record => &$event_data) {
                        foreach ($event_data as $this_event_id => &$field_data) {
                            $keyvalue = $field_data[$attr['field_name']];
                            // if value is not blank
                            if ($keyvalue != '') {
                                $field_data[$attr['field_name']] = $enumvalues[$keyvalue];
                            }
                        }
                    }
                    // Change element type to 'text' so a line is displayed in the PDF if there is no value
                    $attr['element_type'] = "text";
                }
            }

            // Conditionally add to the filtered metadata list
            if ($include) {
                $filtered_metadata[] = $attr;
            }
        }
    }
    return $filtered_metadata;
}