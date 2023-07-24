<?php

namespace SMXD\Application\Traits;

trait ValidationTraits
{

    /**
     * @return array
     */
    public function getMessagesArray()
    {
        $results = [];
        $messages = $this->getMessages();
        foreach ($messages as $message) {
            $results[] = $message->getMessage();
        }
        return $results;
    }

    /**
     * @return string
     */
    public function getFirstMessage()
    {
        $messages = $this->getMessages();
        foreach ($messages as $message) {
            return $message->getMessage();
        }
    }
}

