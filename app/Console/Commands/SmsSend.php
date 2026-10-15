<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\SmsTemplate;
use App\Services\Sms\SmsException;
use Illuminate\Console\Command;
use InvalidArgumentException;

class SmsSend extends Command
{
    protected $signature = 'sms:send
                            {to : Phone number}
                            {template : Template id or key}
                            {--set=* : Placeholder value, as name=value}';

    protected $description = 'Queue an SMS rendered from a template';

    public function handle(): int
    {
        $lookup = (string) $this->argument('template');
        $template = SmsTemplate::query()->where('key', $lookup)->first();

        if (! $template && ctype_digit($lookup)) {
            $template = SmsTemplate::query()->find((int) $lookup);
        }

        if (! $template) {
            $this->error(__('sms_template.errors.missing', ['key' => $lookup]));

            return self::FAILURE;
        }

        $variables = [];

        foreach ((array) $this->option('set') as $pair) {
            [$name, $value] = array_pad(explode('=', (string) $pair, 2), 2, '');
            $name = trim($name);

            if ($name !== '') {
                $variables[$name] = $value;
            }
        }

        try {
            $notification = Notification::queueSms((string) $this->argument('to'), $template, null, $variables);
        } catch (InvalidArgumentException|SmsException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Queued notification #'.$notification->id);

        return self::SUCCESS;
    }
}
