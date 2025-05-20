<?php

namespace App\Dto;

class TopicHierarchyDto implements \JsonSerializable
{
    private string $topic;
    private array $subTopics;

    public function __construct(string $topic, array $subTopics = [])
    {
        $this->topic = $topic;
        $this->subTopics = $subTopics;
    }

    public function getTopic(): string
    {
        return $this->topic;
    }

    public function getSubTopics(): array
    {
        return $this->subTopics;
    }

    public function addSubTopic(string $subTopic): self
    {
        if (!in_array($subTopic, $this->subTopics)) {
            $this->subTopics[] = $subTopic;
        }
        return $this;
    }

    public function jsonSerialize(): array
    {
        return [
            'topic' => $this->topic,
            'subTopics' => $this->subTopics
        ];
    }
}