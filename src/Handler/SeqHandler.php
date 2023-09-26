<?php

declare(strict_types=1);

namespace kricha\MonologDatalustSeq\Handler;

use kricha\MonologDatalustSeq\Formatter\SeqFormatter;
use Monolog\Formatter\FormatterInterface;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Handler\Curl\Util;
use Monolog\Handler\HandlerInterface;
use Monolog\LogRecord;

class SeqHandler extends AbstractProcessingHandler
{
    public const SEQ_API_URI = 'api/events/raw?clef';

    public const SEQ_API_METHOD = 'POST';

    public function __construct(
        private string $serverUri,
        private string $apiKey,
        $level = 100,
        $bubble = true
    ) {
        parent::__construct(
            $level,
            $bubble
        );
    }

    public function setFormatter(FormatterInterface $formatter): HandlerInterface
    {
        if (!($formatter instanceof SeqFormatter)) {
            throw new \InvalidArgumentException('SeqFormatter expected, got '.\gettype($formatter).' / '.\get_class($formatter));
        }

        $this->formatter = $formatter;

        return $this;
    }

    protected function write(LogRecord|array $record): void
    {
        $record = is_array($record) ? $record['formatted'] : $record->formatted;
        $this->send($record);
    }

    protected function send(string $message): void
    {
        $ch = curl_init();
        $url = rtrim($this->serverUri, '/').'/'.self::SEQ_API_URI . '&apiKey='.$this->apiKey;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(json_decode($message)));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type' => 'application/vnd.serilog.clef',
        ]);

        $result = Util::execute($ch);
        if (!is_string($result)) {
            throw new \RuntimeException('Seq API error. No response');
        }
        $result = json_decode($result, true);

        if (array_key_exists('Error', $result)) {
            throw new \RuntimeException('Seq request error: '.$result['Error']);
        }
    }

    protected function getDefaultFormatter(): FormatterInterface
    {
        return new SeqFormatter();
    }
}
