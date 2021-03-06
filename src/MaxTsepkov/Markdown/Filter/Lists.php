<?php
/**
 * Copyright (C) 2011, Maxim S. Tsepkov
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace MaxTsepkov\Markdown\Filter;

use MaxTsepkov\Markdown\Lists\Stack,
    MaxTsepkov\Markdown\Filter,
    MaxTsepkov\Markdown\Text,
    MaxTsepkov\Markdown\Line;

/**
 * Abstract class for all list's types
 *
 * Definitions:
 * <ul>
 *   <li>list items may consist of multiple paragraphs</li>
 *   <li>each subsequent paragraph in a list item
 *      must be indented by either 4 spaces or one tab</li>
 * </ul>
 *
 * @todo Readahead list lines and pass through blockquote and code filters.
 * @package Markdown
 * @subpackage Filter
 * @author Max Tsepkov <max@garygolden.me>
 * @version 1.0
 */
abstract class Lists extends Filter
{
    /**
     * Pass given text through the filter and return result.
     *
     * @see Filter::filter()
     * @param string $text
     * @return string $text
     */
    public function filter(Text $text)
    {
        $stack = new Stack();

        foreach ($text as $no => $line) {
            $prevline = isset($text[$no - 1]) ? $text[$no - 1] : null;
            $nextline = isset($text[$no + 1]) ? $text[$no + 1] : null;

            // match list marker, add a new list item
            if (($marker = $this->matchMarker($line)) !== false) {
                if (!$stack->isEmpty() && $prevline->isBlank() && (!isset($nextline) || $nextline->isBlank())) {
                    $stack->paragraphize();
                }

                $stack->addItem(array($no => substr($line, strlen($marker))));

                continue;
            }

            // we are inside a list
            if (!$stack->isEmpty()) {
                // a blank line
                if ($line->isBlank()) {
                    // two blank lines in a row
                    if ($prevline !== null && $prevline->isBlank()) {
                        // end of list
                        $stack->apply($text, static::TAG);
                    }
                } else { // not blank line
                    if ($line->isIndented()) {
                        // blockquote
                        if (substr(ltrim($line), 0, 1) == '>') {
                            $line->gist = substr(ltrim($line), 1);
                            if (substr(ltrim($prevline), 0, 1) != '>') {
                                $line->prepend('<blockquote>');
                            }
                            if (substr(ltrim($nextline), 0, 1) != '>') {
                                $line->append('</blockquote>');
                            }
                        // codeblock
                        } else if (substr($line, 0, 2) == "\t\t" || substr($line, 0, 8) == '        ') {
                            $line->gist = ltrim(htmlspecialchars($line, ENT_NOQUOTES));
                            if (!(substr($prevline, 0, 2) == "\t\t" || substr($prevline, 0, 8) == '        ')) {
                                $line->prepend('<pre><code>');
                            }
                            if (!(substr($nextline, 0, 2) == "\t\t" || substr($nextline, 0, 8) == '        ')) {
                                $line->append('</code></pre>');
                            }
                        } elseif (!isset($prevline) || $prevline->isBlank()) {
                            // new paragraph inside a list item
                            $line->gist = '</p><p>' . ltrim($line);
                        } else {
                            $line->gist = ltrim($line);
                        }
                    } elseif (!isset($prevline) || $prevline->isBlank()) {
                        // end of list
                        $stack->apply($text, static::TAG);
                        continue;
                    } else { // unbroken text inside a list item
                        // add text to current list item
                        $line->gist = ltrim($line);
                    }

                    $stack->appendLine(array($no => $line));
                }
            }
        }

        // if there is still stack, flush it
        if (!$stack->isEmpty()) {
            $stack->apply($text, static::TAG);
        }

        return $text;
    }

    abstract protected function matchMarker($line);
}
