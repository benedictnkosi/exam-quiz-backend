<?php

namespace App\Command;

use App\Service\PushNotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:send-pro-upgrade-apology',
    description: 'Send apology message to users who did not get Pro on subscription purchase.'
)]
class SendProUpgradeApologyCommand extends Command
{
    private PushNotificationService $pushNotificationService;

    public function __construct(PushNotificationService $pushNotificationService)
    {
        parent::__construct();
        $this->pushNotificationService = $pushNotificationService;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $uids = [
            // Original list
            'T2VIPJvb7Uc2APdKYB1zr7laKOX2',
            'xF5Uwpecs3QPj9USoaxWh50JGpK2',
            'v58vcukuGcVTtYqFCEqJViDmzrJ2',
            'jqsySjx1CNWPoyRUP7FM3CMMd4s2',
            'uQ4gfUnUMuf2gOEWvCbzqwUBQrr1',
            'SwfAzW46ymPWqn3RvP5rBr8oV2t1',
            'aTslZb6rFGbLQvjHnJ3Gxnx0xTw2',
            'zmSkg6SzFWXK2YF2RM3O6ENTVPh1',
            'Qx624978gdU63MWqI7D9vrhtWLf2',
            'xX2CsPNZ84g3DnNNSLZrd37YNTl1',
            // Additional users
            'xJGWjYlli1W5Pv0jkPM8YfC6VM53',
            'w77MQSCw2nhhI0f5PYhu2Za8jg23',
            'RRxipkMYDdhkcY9BK298U4OkXMF2',
            'lK3jYD9Gj7Q8k6iDaw1ZIwOdlVh2',
            'IvcAxrfVG3ZQpOk7Z2RUXD91kvO2',
            'apFmYRHJq2dEzcPhsfJHVyvmcE93',
            'fLm2DIki5kShylm47EzSR5x7nnh1',
        ];
        $title = '🙏 Sorry for the delay!';
        $message = 'You have now been given 3 months of Pro access. We apologize that your account was not upgraded to Pro when you purchased your subscription. Thank you for your patience!';

        $output->writeln('<info>Sending apology message to users...</info>');
        $result = $this->pushNotificationService->sendCustomMessageToUids($uids, $message, $title);
        $output->writeln('<comment>Result:</comment>');
        $output->writeln(print_r($result, true));
        $output->writeln('<info>Done.</info>');
        return Command::SUCCESS;
    }
} 