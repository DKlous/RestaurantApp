<?php

namespace App\Form;

use App\Entity\Reservation;
use App\Entity\Table;
use App\Repository\TableRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

final class ReservationFormType extends AbstractType
{
    public function __construct(
        private readonly TableRepository $tables,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array<string, Table> $choices */
        $choices = [];
        foreach ($this->tables->findByActive() as $table) {
            $choices[$table->getName()] = $table;
        }

        $builder
            ->add('table', ChoiceType::class, [
                'choices' => $choices,
                'placeholder' => 'staff.choose_table',
                'label' => 'staff.table',
                'choice_translation_domain' => false,
                'required' => true,
                'constraints' => [new NotBlank()],
                'setter' => static function (Reservation $reservation, ?Table $table): void {
                    if (null !== $table) {
                        $reservation->setTable($table);
                    }
                },
            ])
            ->add('date', DateType::class, [
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'input_format' => 'Y-m-d',
                'required' => true,
                'label' => 'staff.date',
                'constraints' => [new NotBlank()],
                'setter' => static function (Reservation $reservation, ?\DateTimeImmutable $date): void {
                    if (null !== $date) {
                        $reservation->setDate($date);
                    }
                },
            ])
            ->add('time', TimeType::class, [
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'input_format' => 'H:i',
                'required' => true,
                'label' => 'staff.start_time',
                'constraints' => [new NotBlank()],
                'setter' => static function (Reservation $reservation, ?\DateTimeImmutable $time): void {
                    if (null !== $time) {
                        $reservation->setTime($time);
                    }
                },
            ])
            ->add('guests', IntegerType::class, [
                'required' => true,
                'label' => 'staff.guests',
                'attr' => ['min' => 1, 'max' => 100],
                'constraints' => [new NotBlank()],
                'setter' => static function (Reservation $reservation, ?int $guests): void {
                    if (null !== $guests) {
                        $reservation->setGuests($guests);
                    }
                },
            ])
            ->add('durationMinutes', IntegerType::class, [
                'required' => true,
                'label' => 'staff.duration_label',
                'attr' => ['min' => 15, 'max' => 1440, 'step' => 15],
                'constraints' => [new NotBlank(), new Range(min: 15, max: 1440)],
                'setter' => static function (Reservation $reservation, ?int $durationMinutes): void {
                    if (null !== $durationMinutes) {
                        $reservation->setDurationMinutes($durationMinutes);
                    }
                },
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Reservation::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'reservation_create',
        ]);
    }
}
