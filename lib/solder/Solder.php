<?php
/*

   INTRODUCTION
   ------------

   Solder is a very simple templating system for PHP. It supports variable 
   references, character escaping, references to other templates and
   internationalisation.
   
   Solder is Copyright (C) 2014, 2015, 2016 Elod Csirmaz
   <http://www.epcsirmaz.com/> <https://github.com/csirmaz>.

   Version 20

   The MIT License (MIT)

   Permission is hereby granted, free of charge, to any person obtaining a copy
   of this software and associated documentation files (the "Software"), to deal
   in the Software without restriction, including without limitation the rights
   to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
   copies of the Software, and to permit persons to whom the Software is
   furnished to do so, subject to the following conditions:

   The above copyright notice and this permission notice shall be included in
   all copies or substantial portions of the Software.

   THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
   IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
   FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
   AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
   LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
   OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
   THE SOFTWARE.
   
   USAGE AND SYNTAX
   ----------------
   
   Multiple templates can be stored in a file which is loaded when the 
   Solder object is constructed, and then parsed fully on-demand. In the
   file, templates are separated by headings of the form
   
      <separator> <template_label>
      
   or
   
      <separator> <template_label> <language>

   where <separator> is the first word (any characters up to the first
   whitespace) in the first line of the template file. The <separator>s cannot be
   preceded by whitespace.

   Templates with labels starting with "--" are comments and are ignored.

   Inside a template, a number of directives can be defined:

   Variable references whose value is given when calling fuse():

      {/variable_name/}

   Variable references with one or more character escaping specified that
   will be applied to the value of the variable:

      {/variable_name:escaping/}
      {/variable_name:escaping,escaping/}

   A cross-reference to another template:
   
      {/<template_label/}

   A comment:
   
      {--comment--}

   A special directive that is replaced with a space. Use this at the end of
   a template as by default, trailing whitespace is trimmed:
   
      {---}

   Internationalisation is handled by specifying the language for some of
   the templates.

   - Templates without a language specified are always parsed
   
   - Templates with a language specified are only parsed if the language label
     matches the language passed to the constructor
     
   This makes it possible to define alternative templates for each language,
   and incorporate them in the output either via template references or via
   code.
   
   EXAMPLE
   -------
   
   Please see example.php and example.solder
   
   INTERNAL DATA
   -------------

   Internally, templates are stored in the following way:

   $this->templates = array( <LABEL> => <TEMPLATE>, ... )

   <TEMPLATE>       = <RAWTEMPLATE> | <PARSEDTEMPLATE>
   <RAWTEMPLATE>    = <string>
   <PARSEDTEMPLATE> = array( <ELEMENT>* )

   <ELEMENT>     = <string> | <VARIABLE>
   <VARIABLE>    = array( 'name' => <VARIABLENAME> [, 'esc' => array( <ESCAPINGNAME>* )] )

*/

class Solder {

   private $language;
   private $templates = array();

   /** Reads the templates from the given file, and parses them into RESTTEMPLATEs for future use */
   public function __construct($filename, $language) {

      if(!mb_regex_encoding('UTF-8')) {
         throw new Exception('Cannot change regex encoding');
      }

      // Read the template file
      $FH = fopen($filename, 'r');
      if($FH === false) {
         throw new Exception('Cannot open ' . $filename);
      }

      $separator = false;
      $separator_length = false;
      $templatelabel = false;
      $template = '';
      while(!feof($FH)) {
         $line = fgets($FH);
         
         // Extract the separator
         if($separator === false) {
            $line_parts = preg_split('/\s+/', $line);
            if(count($line_parts) == 0 || ! $line_parts[0]) {
               throw new Exception('Cannot locate separator on the first line');
            }
            $separator = $line_parts[0];
            $separator_length = strlen($separator);
         }

         if(substr($line, 0, $separator_length) == $separator) {
            $this->storetempl($templatelabel, $template, $language); // store the previous template
            $template = '';
            $templatelabel = substr($line, $separator_length);

            if(!$templatelabel) {
               throw new Exception('Missing label in ' . $filename);
            }

         } else {

            $template .= $line;

         }
      }
      fclose($FH);
      $this->storetempl($templatelabel, $template, $language);
   }

   /** Interpolates values into the variables in a template.
    * By default, $values is an array with the variable names as keys.
    * If it is a scalar, it is used as the value of the first variable.
    */
   public function fuse($templatelabel, $values = null) {
      $out = '';
      $this->parsetempl($templatelabel);

      // Shortcut for when the template is static and has a single element only
      if($values === null) {
         $e = $this->templates[$templatelabel];
         if(count($e) == 1 && !is_array($e[0])) {
            return $e[0];
         }
      }
      
      foreach($this->templates[$templatelabel] as $e) {

         if(is_array($e)) { // variable
            if(is_array($values)) {
               if(!array_key_exists($e['name'], $values)) {
                  throw new Exception('No value given to "' . $e['name'] . '" in "' . $templatelabel . '"');
               }
               $x = $values[$e['name']];
            } else { // scalar value
               if($values === null) {
                  throw new Exception('No value given to "' . $e['name'] . '" in "' . $templatelabel . '"');
               }
               $x = $values;
               $values = null;
            }

            if(array_key_exists('esc', $e)) {
               foreach($e['esc'] as $t) {
                  $x = self::escape($x, $t);
               }
            }
            $out .= $x;
         } else {
            $out .= $e;
         }
      }
      return $out;
   }

   private function storetempl($templatelabel, $template, $language) {
      if($templatelabel === false) {
         return;
      }

      $templatelabel = trim($templatelabel);
      if(strlen($templatelabel) == 0) {
         return;
      }
      
      // Comment template
      if(substr($templatelabel, 0, 2) == '--') {
         return;
      }

      // Only store the template with the correct language
      $templatelabel = preg_split('/\s+/', $templatelabel);
      if(count($templatelabel) > 1 && $language != $templatelabel[1]) {
         return;
      }

      $this->templates[$templatelabel[0]] = $template;
   }

   /** Parses a RAWTEMPLATE into a PARSEDTEMPLATE */
   private function parsetempl($templatelabel) {
      // Return if it is already parsed
      if(is_array($this->templates[$templatelabel])) {
         return $this->templates[$templatelabel];
      }

      if(!array_key_exists($templatelabel, $this->templates)) {
         throw new Exception('Template ' . $templatelabel . ' is not defined');
      }

      $templ = $this->templates[$templatelabel];

      // Remove trailing space
      $templ = rtrim($templ);
      $templ = str_replace('{---}', ' ', $templ);

      // Remove comments
      $templ = preg_replace('/\{--(.*?)--\}/', '', $templ);

      // Parse active elements
      $newtempl = array();
      $templ = preg_split('/\{\/\s*(.*?)\s*\/\}/', $templ, NULL, PREG_SPLIT_DELIM_CAPTURE);
      $isvar = false;
      foreach($templ as $e) {
         if($isvar) {
            if(substr($e, 0, 1) == '<') { // Cross-reference
               $newtempl = array_merge($newtempl, $this->parsetempl(trim(substr($e, 1))));
               // Here we could merge neighbouring static elements
            } elseif (preg_match('/^(.*?)\s*:\s*(.*?)$/', $e, $matches)) { // If there are escaping methods specified
               $newtempl[] = array('name' => $matches[1], 'esc' => preg_split('/\s*,\s*/', $matches[2]));
            } else {
               $newtempl[] = array('name' => $e);
            }
         } else {
            $newtempl[] = $e;
         }
         $isvar = !$isvar;
      }
      $this->templates[$templatelabel] = $newtempl;
      return $newtempl;
   }

   /** Escapes a string using the given method */
   public static function escape($string, $escname) {
      switch($escname) {
         case 'h': // HTML - Will return wide characters as-is
            return htmlspecialchars($string, ENT_NOQUOTES | ENT_HTML5, 'UTF-8', true);
         case 'q': // HTML-Quotes, use in attributes - Will return wide characters as-is
            return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8', true);
         case 'js': // JS string literal with both ' and " escaped
            $string = mb_ereg_replace('[\'"\\\\]', '\\\\0', $string);
            $string = mb_ereg_replace('\r?\n', '\\n', $string);
            $string = mb_ereg_replace_callback(
               '[^\x20-\x7E]|<|>|&',
               function ($m) {
                  return '\u' . str_pad(bin2hex($m[0]), 4, '0', STR_PAD_LEFT);
               },
               $string
            );
            return $string;
         case 'json': // JSON string literal
            $string = mb_ereg_replace('["\\\\]', '\\\\0', $string);
            $string = mb_ereg_replace('\r?\n', '\\u000a', $string);
            $string = mb_ereg_replace_callback(
               '[^\x20-\x7E]|<|>|&',
               function ($m) {
                  return '\u' . str_pad(bin2hex($m[0]), 4, '0', STR_PAD_LEFT);
               },
               $string
            );
            return $string;
         case 'csv':
            $string = mb_ereg_replace('["\\\\]', '\\\\0', $string);
            $string = mb_ereg_replace('\r?\n', ' ', $string);
            $string = '"' . $string . '"';
            return $string;
         case 'u':
            $string = rawurlencode($string);
            return $string;
         default:
            throw new Exception('Unknown escaping "' . $escname . '"');
      }
   }

   public function debug() {
      print_r($this->templates);
   }

}

?>
