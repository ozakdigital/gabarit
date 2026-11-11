<?php

declare(strict_types=1);

namespace Gabarit\Cli\Interview;

use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Repli sans dépendance au terminal : fonctionne partout, y compris sous Windows natif.
 */
final readonly class ConsoleInterviewer implements Interviewer
{
    public function __construct(private SymfonyStyle $io)
    {
    }

    public function select(string $label, array $options, ?string $default = null): string
    {
        $values = array_keys($options);
        $labels = array_values($options);
        $question = new ChoiceQuestion($label, $labels, null !== $default ? array_search($default, $values, true) : 0);
        $question->setErrorMessage('Choix invalide : %s');

        $answer = $this->io->askQuestion($question);
        $index = array_search($answer, $labels, true);

        return (string) $values[false === $index ? 0 : $index];
    }

    public function multiselect(string $label, array $options): array
    {
        if ([] === $options) {
            return [];
        }
        $values = array_keys($options);
        $labels = ['Aucun', ...array_values($options)];
        $question = new ChoiceQuestion($label.' (séparez par des virgules)', $labels, 0);
        $question->setMultiselect(true);

        /** @var list<string> $answers */
        $answers = (array) $this->io->askQuestion($question);
        $selected = [];
        foreach ($answers as $answer) {
            $index = array_search($answer, $labels, true);
            if (false !== $index && $index > 0) {
                $selected[] = (string) $values[$index - 1];
            }
        }

        return array_values(array_unique($selected));
    }

    public function text(string $label, string $default, \Closure $validate): string
    {
        $question = new Question($label, $default);
        $question->setValidator(static function (mixed $value) use ($validate): string {
            $value = trim((string) $value);
            $error = $validate($value);
            if (null !== $error) {
                throw new \InvalidArgumentException($error);
            }

            return $value;
        });

        return (string) $this->io->askQuestion($question);
    }
}
