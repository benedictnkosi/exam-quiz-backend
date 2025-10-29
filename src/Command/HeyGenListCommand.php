<?php

namespace App\Command;

use App\Service\HeyGenService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:heygen:list',
    description: 'List HeyGen avatars and voices'
)]
class HeyGenListCommand extends Command
{
    public function __construct(private HeyGenService $heyGenService)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Fetching HeyGen avatars...</info>');
        $avatars = $this->heyGenService->listAvatars();
        if (is_array($avatars)) {
            $list = $avatars['data']['avatars'] ?? ($avatars['data'] ?? ($avatars['avatars'] ?? []));
            $count = is_countable($list) ? count($list) : 0;
            $output->writeln('<info>Avatars found: ' . $count . '</info>');
            foreach ($list as $a) {
                $id = $a['avatar_id'] ?? $a['id'] ?? $a['uuid'] ?? 'id';
                $name = $a['avatar_name'] ?? $a['name'] ?? $a['display_name'] ?? $a['label'] ?? 'name';
                $gender = $a['gender'] ?? null;
                $img = $a['preview_image_url'] ?? null;
                $out = [$id, $name];
                if ($gender) { $out[] = $gender; }
                if ($img) { $out[] = $img; }
                $output->writeln(implode(' | ', $out));
            }
        } else {
            $output->writeln('<comment>Failed to fetch avatars.</comment>');
        }

        $output->writeln('');
        $output->writeln('<info>Fetching HeyGen voices...</info>');
        $voices = $this->heyGenService->listVoices();
        if (is_array($voices)) {
            $list = $voices['data']['voices'] ?? ($voices['data'] ?? ($voices['voices'] ?? []));
            $count = is_countable($list) ? count($list) : 0;
            $output->writeln('<info>Voices found: ' . $count . '</info>');
            foreach ($list as $v) {
                $id = $v['voice_id'] ?? $v['id'] ?? $v['uuid'] ?? 'id';
                $name = $v['name'] ?? $v['display_name'] ?? $v['label'] ?? 'name';
                $lang = $v['language'] ?? $v['locale'] ?? null;
                $gender = $v['gender'] ?? null;
                $audio = $v['preview_audio'] ?? null;
                $parts = [$id, $name];
                if ($lang) { $parts[] = $lang; }
                if ($gender) { $parts[] = $gender; }
                if ($audio) { $parts[] = $audio; }
                $output->writeln(implode(' | ', $parts));
            }
        } else {
            $output->writeln('<comment>Failed to fetch voices.</comment>');
        }

        return Command::SUCCESS;
    }
}


