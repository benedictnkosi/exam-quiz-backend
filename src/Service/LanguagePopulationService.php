<?php

namespace App\Service;

use App\Entity\Languages;
use Doctrine\ORM\EntityManagerInterface;

class LanguagePopulationService
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function populateLanguages(): void
    {
        $languages = [
            ['code' => 'af', 'enabled' => true, 'name' => 'Afrikaans', 'nativeName' => 'Afrikaans'],
            ['code' => 'en', 'enabled' => true, 'name' => 'English', 'nativeName' => 'English'],
            ['code' => 'nr', 'enabled' => true, 'name' => 'Southern Ndebele', 'nativeName' => 'isiNdebele'],
            ['code' => 'nso', 'enabled' => true, 'name' => 'Northern Sotho', 'nativeName' => 'Sepedi'],
            ['code' => 'ss', 'enabled' => true, 'name' => 'Swati', 'nativeName' => 'siSwati'],
            ['code' => 'st', 'enabled' => true, 'name' => 'Southern Sotho', 'nativeName' => 'Sesotho'],
            ['code' => 'tn', 'enabled' => true, 'name' => 'Tswana', 'nativeName' => 'Setswana'],
            ['code' => 'ts', 'enabled' => true, 'name' => 'Tsonga', 'nativeName' => 'Xitsonga'],
            ['code' => 've', 'enabled' => true, 'name' => 'Venda', 'nativeName' => 'Tshivenda'],
            ['code' => 'xh', 'enabled' => true, 'name' => 'Xhosa', 'nativeName' => 'isiXhosa'],
            ['code' => 'zu', 'enabled' => true, 'name' => 'Zulu', 'nativeName' => 'isiZulu'],
        ];

        foreach ($languages as $langData) {
            $existing = $this->em->getRepository(Languages::class)->findOneBy(['code' => $langData['code']]);
            if (!$existing) {
                $lang = new Languages();
                $lang->setCode($langData['code']);
                $lang->setEnabled($langData['enabled']);
                $lang->setName($langData['name']);
                $lang->setNativeName($langData['nativeName']);
                $this->em->persist($lang);
            }
        }
        $this->em->flush();
    }
}