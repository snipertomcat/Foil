<?php
/*
 * This file is part of the Foil package.
 *
 * (c) Giuseppe Mazzapica <giuseppe.mazzapica@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Foil\Kernel;

use Foil\Contracts\EscaperInterface;
use Aura\Html\Escaper as AuraHtmlEscaper;
use Traversable;

/**
 * @author  Giuseppe Mazzapica <giuseppe.mazzapica@gmail.com>
 * @license http://opensource.org/licenses/MIT MIT
 * @package Foil
 */
class Escaper implements EscaperInterface
{
    /**
     * @var string
     */
    private $encoding;

    /**
     * @var \Aura\Html\Escaper
     */
    private $escaper;

    /**
     * @var array
     */
    private $escapers;

    /**
     * @param \Aura\Html\Escaper $escaper
     * @param string             $encoding
     */
    public function __construct(AuraHtmlEscaper $escaper, $encoding = 'utf8')
    {
        $this->escaper = $escaper;
        $this->encoding = strtolower($encoding);
        $this->escaper->setEncoding($this->encoding);
        $this->escapers[$this->encoding] = $escaper;
    }

    /**
     * @param  mixed       $data
     * @param  string      $strategy
     * @param  string|null $encoding
     * @return mixed
     */
    public function escape($data, $strategy = 'html', $encoding = null)
    {
        $method = 'escape'.ucfirst(gettype($data));
        if (! in_array($strategy, ['html', 'js', 'css', 'attr'], true)) {
            $strategy = 'html';
        }

        return method_exists($this, $method)
            ? $this->$method($data, $strategy, $encoding, $this->escaper($encoding))
            : $data;
    }

    /**
     * @param  mixed       $data
     * @param  string|null $encoding
     * @return mixed
     */
    public function decode($data, $encoding = null)
    {
        if (is_string($data)) {
            return html_entity_decode($data, ENT_QUOTES, $encoding ?: $this->encoding);
        } elseif (is_array($data) || $data instanceof Traversable) {
            $result = [];
            foreach ($data as $i => $item) {
                $result[$i] = $this->decode($data, $encoding);
            }

            return $result;
        }

        return is_object($data) && method_exists($data, '__toString')
            ? $this->decode($data->__toString(), $data, $encoding)
            : $data;
    }

    /**
     * @param  string             $encoding
     * @return \Aura\Html\Escaper
     */
    private function escaper($encoding)
    {
        $escaperEncoding = is_string($encoding) ? strtolower($encoding) : $this->encoding;
        if (! array_key_exists($escaperEncoding, $this->escapers)) {
            /** @var \Aura\Html\Escaper $escaper */
            $escaper = clone $this->escaper;
            $escaper->setEncoding($encoding);
            $this->escapers[$escaperEncoding] = $escaper;
        }

        return $this->escapers[$escaperEncoding];
    }

    /**
     * @param  string             $data
     * @param  string             $strategy
     * @param  string             $encoding
     * @param  \Aura\Html\Escaper $escaper
     * @return string|array
     */
    private function escapeString($data, $strategy, $encoding, AuraHtmlEscaper $escaper)
    {
        return $escaper->$strategy($data);
    }

    /**
     * @param  array              $data
     * @param  string             $strategy
     * @param  string             $encoding
     * @param  \Aura\Html\Escaper $escaper
     * @return string|array
     */
    private function escapeArray(array $data, $strategy, $encoding, AuraHtmlEscaper $escaper)
    {
        if ($strategy === 'attr') {
            return $escaper->attr($data);
        }
        array_walk($data, function (&$item) use ($strategy, $encoding) {
            $item = $this->escape($item, $strategy, $encoding);
        });

        return $data;
    }

    /**
     * @param  object             $data
     * @param  string             $strategy
     * @param  string             $encoding
     * @param  \Aura\Html\Escaper $escaper
     * @return string|array
     */
    private function escapeObject($data, $strategy, $encoding, AuraHtmlEscaper $escaper)
    {
        if (method_exists($data, '__toString')) {
            return $this->escapeString($data->__toString(), $strategy, $encoding, $escaper);
        } elseif ($data instanceof Traversable) {
            $result = [];
            foreach ($data as $i => $item) {
                $result[$i] = $this->escape($item, $strategy, $encoding);
            }

            return $result;
        }

        return $strategy === 'attr'
            ? $this->escapeArray(get_object_vars($data), $strategy, $encoding, $escaper)
            : $data;
    }
}
