<?php

declare(strict_types=1);

namespace kricha\MonologDatalustSeq\Formatter;

use Monolog\Formatter\JsonFormatter;

class SeqFormatter extends JsonFormatter
{
    protected $logLevelMap = [
        '100' => 'Debug',
        '200' => 'Information',
        '250' => 'Information',
        '300' => 'Warning',
        '400' => 'Error',
        '500' => 'Error',
        '550' => 'Fatal',
        '600' => 'Fatal',
    ];

    public function __construct(private $extractContext = false, private $extractExtras = false)
    {
        $this->appendNewline = false;
        parent::__construct(JsonFormatter::BATCH_MODE_NEWLINES);
        $this->includeStacktraces = true;
    }

    protected function formatBatchJson(array $records): string
    {
        throw new \Exception('RRRR');
    }

    protected function normalize($record, $depth = 0): mixed
    {
        if (!\is_array($record) && !$record instanceof \Traversable) {
            throw new \InvalidArgumentException('Array/Traversable expected, got '.\gettype($record).' / '.\get_class($record));
        }

        $m = $record['message'];
        $level = $record['level'];

        $normalized = [
            '@m' => $m,
            '@l' => $this->logLevelMap[$level],
            'code' => $level,
            'level_name' => $record['level_name'],
            'channel' => $record['channel'],
            '@t' => $record['datetime']->format(\DateTimeInterface::ATOM),
        ];
        if (str_contains($m, '{')) {
            $normalized['@mt'] = $m;
        }

        if ($record['context']['exception'] ?? null) {
            $normalized['@x'] = $this->normalizeException($record['context']['exception'])[0];
            unset($record['context']['exception']);
        }

        $normalizedExtra = $this->getNormalizedArray($record['extra']);
        if ($this->extractExtras) {
            $normalized = array_merge($normalizedExtra, $normalized);
        } else {
            $normalized['extra'] = $normalizedExtra;
        }

        $normalizedContext = $this->getNormalizedArray($record['context']);
        foreach ($normalizedContext as $k => $v) {
            $placeholder = '{'.$k.'}';
            if (str_contains($m, $placeholder)) {
                $normalized[$k] = $v;
                unset($normalizedContext[$k]);
            }
        }
        if ($this->extractContext) {
            $normalized = array_merge($normalizedContext, $normalized);
        } else {
            $normalized['context'] = $normalizedContext;
        }

        return $normalized;
    }

    protected function normalizeException($e, int $depth = 0): array
    {
        $previousText = '';
        if ($previous = $e->getPrevious()) {
            do {
                $previousText .= ', '.\get_class($previous).'(code: '.$previous->getCode().'): '.$previous->getMessage().' at '.$previous->getFile().':'.$previous->getLine();
            } while ($previous = $previous->getPrevious());
        }

        $str = \get_class($e).' (code: '.$e->getCode().'): '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine().$previousText.')';
        if ($this->includeStacktraces) {
            $str .= "\n[stacktrace]\n".$e->getTraceAsString()."\n";
        }

        return [$str];
    }

    private function getNormalizedArray(array $array): array
    {
        $normalized = [];
        $count = 1;
        foreach ($array as $key => $value) {
            if ($count++ >= 1000) {
                $normalized['...'] = 'Over 1000 items, aborting normalization';
                break;
            }

            if (\is_int($key)) {
                $normalized[] = $value;
            } else {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }
}
