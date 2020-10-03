<?php
declare(strict_types = 1);

namespace App\Component\Whoops;

use Smarty_Internal_Template;
use Smarty_Variable;
use Whoops\Exception\Frame;
use Whoops\Exception\Inspector;

class WhoopsSmartyDataTable
{
    public function __invoke(Inspector $inspector)
    {
        $map = [];
        foreach ($inspector->getFrames() as $frame) {
            assert($frame instanceof Frame);
            foreach ($frame->getArgs() as $arg) {
                if ($arg instanceof Smarty_Internal_Template) {
                    $map[$arg->compiled->filepath] = $arg->source->filepath;
                }
            }
        }

        foreach ($inspector->getFrames() as $frame) {
            assert($frame instanceof Frame);
            $file = $frame->getFile();
            if (isset($map[$file])) {
                $frame->setApplication(true);
                $frame->addComment($map[$file], 'smarty');
            }
        }

        $templates = [];
        $variables = null;

        foreach ($inspector->getFrames() as $frame) {
            assert($frame instanceof Frame);
            foreach ($frame->getArgs() as $arg) {
                if ($arg instanceof Smarty_Internal_Template) {
                    $template = $arg->source->filepath;
                    $templates[$template] = $template;
                    if ($variables === null) {
                        $variables = $arg->tpl_vars;
                    }
                }
            }
        }

        $data = [];
        foreach (array_values($templates) as $i => $template) {
            $data["templates.$i"] = $template;
        }
        foreach ((array)$variables as $name => $val) {
            assert($val instanceof Smarty_Variable);
            $data["variables.$name"] = $val->value;
        }
        return $data;
    }
}
