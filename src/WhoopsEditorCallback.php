<?php
declare(strict_types = 1);

namespace App\Component\Whoops;

class WhoopsEditorCallback
{
    /**
     * @var string
     */
    private $format;

    /**
     * @var array
     */
    private $mapping;

    /**
     * e.g.)
     *  $handler = new PrettyPageHandler();
     *  $handler->setEditor(new WhoopsEditorCallback('vscode://file/%file:%line', [
     *      '/mnt/c/Users/' => 'C:/Users/',
     *      '/mnt/d/Users/' => 'D:/Users/',
     *  ]));
     */
    public function __construct(string $format, array $mapping)
    {
        $this->format = $format;
        $this->mapping = $mapping;
    }

    public function __invoke($file, $line)
    {
        $patterns = [];
        foreach (array_keys($this->mapping) as $from) {
            $patterns[] = preg_quote($from, '%');
        }
        $patterns = implode('|', $patterns);
        $patterns = "%^($patterns)%";
        $file = preg_replace_callback($patterns, function ($m) {
            return $this->mapping[$m[0]] ?? $m[0];
        }, $file);
        return strtr($this->format, [
            '%file' => rawurlencode((string)$file),
            '%line' => rawurlencode((string)$line),
        ]);
    }
}
