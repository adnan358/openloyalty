<?php
/**
 * Copyright © 2017 Divante, Inc. All rights reserved.
 * See LICENSE for license details.
 */
namespace OpenLoyalty\Bundle\EarningRuleBundle\Form\Type;

use OpenLoyalty\Bundle\EarningRuleBundle\Form\DataTransformer\PosDataTransformer;
use OpenLoyalty\Bundle\EarningRuleBundle\Model\EarningRule;
use OpenLoyalty\Bundle\EarningRuleBundle\Form\DataTransformer\LevelsDataTransformer;
use OpenLoyalty\Bundle\EarningRuleBundle\Form\DataTransformer\SegmentsDataTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Class CreateEarningRuleFormType.
 */
class CreateEarningRuleFormType extends BaseEarningRuleFormType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('type', ChoiceType::class, [
            'required' => true,
            'constraints' => [new NotBlank()],
            'choices' => [
                'General spending rule' => EarningRule::TYPE_POINTS,
                'Event rule' => EarningRule::TYPE_EVENT,
                'Custom event rule' => EarningRule::TYPE_CUSTOM_EVENT,
                'Product purchase' => EarningRule::TYPE_PRODUCT_PURCHASE,
                'Multiply earned points' => EarningRule::TYPE_MULTIPLY_FOR_PRODUCT,
                'Multiply earned points by labels' => EarningRule::TYPE_MULTIPLY_BY_PRODUCT_LABELS,
                'Referral' => EarningRule::TYPE_REFERRAL,
            ],
        ]);

        $builder->add('name', TextType::class, ['required' => true, 'constraints' => [new NotBlank()]]);
        $builder->add('description', TextareaType::class, ['required' => true, 'constraints' => [new NotBlank()]]);
        $builder->add('target', ChoiceType::class, [
                'required' => false,
                'choices' => [
                    'level' => 'level',
                    'segment' => 'segment',
                ],
                'mapped' => false,
            ]);
        $builder->add(
                $builder->create('levels', CollectionType::class, [
                    'entry_type' => TextType::class,
                    'allow_add' => true,
                    'allow_delete' => true,
                    'error_bubbling' => false,
                    'constraints' => [
                        new Callback([$this, 'validateTarget']),
                    ],
                ])->addModelTransformer(new LevelsDataTransformer())
            );
        $builder->add(
            $builder->create('segments', CollectionType::class, [
                'entry_type' => TextType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'error_bubbling' => false,
                'constraints' => [
                    new Callback([$this, 'validateTarget']),
                ],
            ])->addModelTransformer(new SegmentsDataTransformer())
            );
        $builder->add(
            $builder->create('pos', CollectionType::class, [
                'entry_type' => TextType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'error_bubbling' => false,
            ])
            ->addModelTransformer(new PosDataTransformer())
        );
        $builder->add('active', CheckboxType::class, ['required' => false]);
        $builder->add('allTimeActive', CheckboxType::class, ['required' => false]);
        $builder->add('startAt', DateTimeType::class, [
                'required' => true,
                'widget' => 'single_text',
                'format' => DateTimeType::HTML5_FORMAT,
            ]);
        $builder->add('endAt', DateTimeType::class, [
                'required' => true,
                'widget' => 'single_text',
                'format' => DateTimeType::HTML5_FORMAT,
            ]);
        $builder->addEventListener(FormEvents::PRE_SUBMIT, [$this, 'configureFieldsBasedOnType']);
    }

    /**
     * {@inheritdoc}
     */
    public function configureFieldsBasedOnType(FormEvent $event)
    {
        $data = $event->getData();
        $form = $event->getForm();
        if (!isset($data['type'])) {
            $form->get('type')->addError(new FormError((new NotBlank())->message));

            return;
        }
        $type = $data['type'];
        if ($type == EarningRule::TYPE_POINTS) {
            $form
                ->add('pointValue', NumberType::class, [
                    'scale' => 2,
                    'required' => true,
                    'constraints' => [new NotBlank()],
                ])
                ->add('excludedSKUs', ExcludedSKUsFormType::class)
                ->add('excludedLabels', ExcludedLabelsFormType::class)
                ->add('excludeDeliveryCost', CheckboxType::class, [
                    'required' => false,
                ])
                ->add('minOrderValue', NumberType::class);
        } elseif ($type == EarningRule::TYPE_EVENT) {
            $form
                ->add('eventName', TextType::class, [
                    'required' => true,
                    'constraints' => [new NotBlank()],
                ])
                ->add('pointsAmount', NumberType::class, [
                    'scale' => 2,
                    'required' => true,
                    'constraints' => [new NotBlank()],
                ]);
        } elseif ($type == EarningRule::TYPE_CUSTOM_EVENT) {
            $form
                ->add('eventName', TextType::class, [
                    'required' => true,
                    'constraints' => [new NotBlank()],
                ])
                ->add('pointsAmount', NumberType::class, [
                    'scale' => 2,
                    'required' => true,
                    'constraints' => [new NotBlank()],
                ])
                ->add('limit', EarningRuleLimitFormType::class);
        } elseif ($type == EarningRule::TYPE_REFERRAL) {
            $form
                ->add('eventName', TextType::class, [
                    'required' => true,
                    'constraints' => [new NotBlank()],
                ])
                ->add('rewardType', TextType::class, [
                    'required' => true,
                    'constraints' => [new NotBlank()],
                ])
                ->add('pointsAmount', NumberType::class, [
                    'scale' => 2,
                    'required' => true,
                    'constraints' => [new NotBlank()],
                ]);
        } elseif ($type == EarningRule::TYPE_PRODUCT_PURCHASE) {
            $form
                ->add('skuIds', CollectionType::class, [
                    'allow_add' => true,
                    'allow_delete' => true,
                    'entry_type' => TextType::class,
                    'error_bubbling' => false,
                    'constraints' => [new Count(['min' => 1])],
                ])
                ->add('pointsAmount', NumberType::class, [
                    'scale' => 2,
                    'required' => true,
                    'constraints' => [new NotBlank()],
                ]);
        } elseif ($type == EarningRule::TYPE_MULTIPLY_FOR_PRODUCT) {
            $form
                ->add('skuIds', CollectionType::class, [
                    'allow_add' => true,
                    'allow_delete' => true,
                    'entry_type' => TextType::class,
                ])
                ->add('multiplier', NumberType::class, [
                    'required' => true,
                    'scale' => 2,
                    'constraints' => [new NotBlank()],
                ])
                ->add('labels', LabelsFormType::class);
        } elseif ($type == EarningRule::TYPE_MULTIPLY_BY_PRODUCT_LABELS) {
            $form
                ->add('labelMultipliers', CollectionType::class, [
                    'allow_add' => true,
                    'allow_delete' => true,
                    'entry_type' => LabelMultipliersFormType::class,
                    'error_bubbling' => false,
                    'constraints' => [new Count(['min' => 1])],
                ]);
        }
        if (!isset($data['target'])) {
            return;
        }
        $target = $data['target'];
        if ($target == 'level') {
            $data['segments'] = [];
        } elseif ($target == 'segment') {
            $data['levels'] = [];
        }
        $event->setData($data);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => EarningRule::class,
        ]);
    }
}
