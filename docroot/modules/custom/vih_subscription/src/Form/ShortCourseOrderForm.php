<?php
/**
 * @file
 * Contains \Drupal\vih_subscription\Form\ShortCourseOrderForm.
 */

namespace Drupal\vih_subscription\Form;

use Drupal\bellcom_quickpay_integration\Misc\BellcomQuickpayClient;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Field\Plugin\Field\FieldFormatter;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\vih_subscription\Misc\VihSubscriptionUtils;


/**
 * Implements an example form.
 */
class ShortCourseOrderForm extends FormBase {
  protected $course;
  protected $courseOrder;
  protected $price;

  /**
   * Returns page title
   */
  public function getTitle() {
    return $this->t('Tilmelding');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'vih-subscription-short-course-order-form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $course = NULL) {
    //START VARIABLES INIT //
    $this->course = $course;
    $this->price = $course->field_vih_sc_price->value;

    $optionGroups = array();
    $optionGroupOptions = array();
    $optionGroupSuboptions = array();
    foreach ($course->field_vih_sc_option_groups->referencedEntities() as $optionGroupDelta => $optionGroup) {
      $optionGroups[$optionGroupDelta] = $optionGroup->field_vih_og_title->value;

      foreach ($optionGroup->field_vih_og_options->referencedEntities() as $optionDelta => $option) {
        $optionGroupOptions[$optionGroupDelta][$optionDelta] = $option->field_vih_option_title->value;

        foreach ($option->field_vih_option_suboptions as $suboptionDelta => $suboption) {
          $optionGroupSuboptions[$optionGroupDelta][$optionDelta][$suboptionDelta] = $suboption->value;
        }

      }
    }
    $form_state->set('optionGroupOptions', $optionGroupOptions);
    $form_state->set('optionGroupSuboptions', $optionGroupSuboptions);
    //END VARIABLES INIT //

    //START GENERAL DATA //
    $form['price'] = array(
      '#markup' => number_format($this->price, 2)
    );

    $form['status_messages'] = [
      '#type' => 'status_messages',
      '#weight' => -10,
      '#prefix' => '<div id="messages">',
      '#suffix' => '</div>'
    ];

    $form['#course'] = array(
      'title' => $course->getTitle()
    );
    //END GENERAL DATA //

    //START ADDED OPTIONS CONTAINER //
    $form['addedOptionsContainer'] = array(
      '#type' => 'container',
      '#prefix' => '<div id="added-options-container-wrapper">',
      '#suffix' => '</div>',
    );

    $addedOptions = $form_state->get('addedOptions');
    if ($addedOptions) {
      $form['addedOptionsContainer'][] = array(
        '#markup' => $this->t('Added options'),
        '#prefix' => '<h4>',
        '#suffix' => '</h4>',
      );

      foreach ($addedOptions as $addeOptionDelta => $addedOption) {
        $groupOptionName = $optionGroups[$addedOption['optionGroup']];
        $optionName = $optionGroupOptions[$addedOption['optionGroup']][$addedOption['option']];
        $suboptionName = $optionGroupSuboptions[$addedOption['optionGroup']][$addedOption['option']][$addedOption['suboption']];
        $amount = $addedOption['amount'];

        $form['addedOptionsContainer']['addedOption-' . $addeOptionDelta] = [
          '#markup' => $groupOptionName . ' / ' . $optionName . ' / ' . $suboptionName . ' stk. :' . $amount
        ];

        $form['addedOptionsContainer']['deleteAddedOption-' . $addeOptionDelta] = [
          '#type' => 'submit',
          '#value' => $this->t('Slet'),
          '#submit' => array('::removeOption'),
          '#ajax' => [
            'callback' => '::ajaxAddRemoveOptionCallback',
            'wrapper' => 'added-options-container-wrapper',
            'progress' => array(
              'type' => 'none'
            )
          ],
          '#addedOptionDelta' => $addeOptionDelta,
          '#limit_validation_errors' => array(),
          '#button_type' => 'danger',
        ];

        $form['addedOptionsContainer'][] = [
          '#markup' => '<br/>'
        ];
      }
    }
    //END ADDED OPTIONS CONTAINER //

    //START AVAILABLE OPTIONS CONTAINER //
    $form['availableOptionsContainer'] = array(
      '#type' => 'container',
      '#prefix' => '<div id="available-options-container-wrapper" class="form-group">',
      '#suffix' => '</div>',
    );

    $form['availableOptionsContainer']['optionGroups'] = array(
      '#title' => $this->t('Option name'),
      '#type' => 'select',
      '#options' => $optionGroups,
      //'#value' => -1,
      '#empty_value' => -1,
      //'#required' => TRUE,
      '#ajax' => [
        'callback' => '::ajaxFillOptionsList',
        'wrapper' => 'options-container-wrapper',
        'event' => 'change',
        'progress' => array(
          'type' => 'none'
        )
      ],
    );

    $form['availableOptionsContainer']['optionsContainer'] = array(
      '#type' => 'container',
      '#prefix' => '<div id="options-container-wrapper">',
      '#suffix' => '</div>',
      '#states' => [
        'invisible' => [
          [':input[name="optionGroups"]' => ['value' => '-1' ]],
        ]
      ]
    );

    $form['availableOptionsContainer']['optionsContainer']['options'] = array(
      '#title' => $this->t('Selected Option'),
      '#type' => 'select',
      '#value' => -1,
      '#empty_value' => -1,
      //'#required' => TRUE,
      '#ajax' => [
        'callback' => '::ajaxFillSuboptionsList',
        'wrapper' => 'suboptions-container-wrapper',
        'event' => 'change',
        'progress' => array(
          'type' => 'none'
        )
      ],
    );
    //filling the list of options
    $this->ajaxFillOptionsList($form, $form_state);

    $form['availableOptionsContainer']['optionsContainer']['suboptionsContainer'] = array(
      '#type' => 'container',
      '#prefix' => '<div id="suboptions-container-wrapper">',
      '#suffix' => '</div>',
    );

    $form['availableOptionsContainer']['optionsContainer']['suboptionsContainer']['suboptions'] = array(
      '#title' => $this->t('Selected suboption'),
      '#type' => 'select',
      '#value' => NULL,
      '#empty_value' => -1,
      '#access' => FALSE//hidden unless has some options
    );

    $form['availableOptionsContainer']['optionsContainer']['suboptionsContainer']['amount'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Amount'),
      '#value' => 0,
    );

    $form['addOption'] = array(
      '#id' => 'add-option',
      '#name' => 'add-option',
      '#type' => 'submit',
      '#value' => $this->t('Add option'),
      '#submit' => array('::addOption'),
      '#ajax' => [
        'callback' => '::ajaxAddRemoveOptionCallback',
        'wrapper' => 'added-options-container-wrapper',
        'progress' => array(
          'type' => 'none'
        )
      ],
      '#limit_validation_errors' => array(
        array('optionGroups'),
        array('options')
      )
    );
    //END AVAILABLE OPTIONS CONTAINER //

    //START PARTICIPANTS CONTAINER //
    $participantsCounter = $form_state->get('participantsCounter');
    if (empty($participantsCounter)) {
      $participantsCounter = 1;
      $form_state->set('participantsCounter', $participantsCounter);
    }

    $form['participantsContainer'] = [
      '#type' => 'container',
      '#prefix' => '<div id="participants-container-wrapper">',
      '#suffix' => '</div>',
      '#tree' => TRUE
    ];

    for ($i = 0; $i < $participantsCounter; $i++) {
      $personHumanCounter = $i+1;//starting from 1, not 0

      $form['participantsContainer'][$i]['participant_fieldset'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Person') . ' ' . $personHumanCounter
      ];
      $form['participantsContainer'][$i]['participant_fieldset']['firstName'] = array(
        '#type' => 'textfield',
        '#placeholder' => $this->t('Fornavn'),
        '#required' => TRUE,
      );
      $form['participantsContainer'][$i]['participant_fieldset']['lastName'] = array(
        '#type' => 'textfield',
        '#placeholder' => $this->t('Efternavn'),
        '#required' => TRUE,
      );
      $form['participantsContainer'][$i]['participant_fieldset']['email'] = array(
        '#type' => 'textfield',
        '#placeholder' => $this->t('Email'),
        '#required' => TRUE,
      );
    }

    $form['participantsContainer']['actions']['add_participant'] = [
      '#type' => 'submit',
      '#value' => t('Tilføj flere'),
      '#submit' => array('::addParticipant'),
      '#ajax' => [
        'callback' => '::addRemoveParticipantsCallback',
        'wrapper' => 'participants-container-wrapper',
      ],
      '#limit_validation_errors' => array()
    ];

    if ($participantsCounter > 1) {
      $form['participantsContainer']['actions']['remove_participant'] = [
        '#type' => 'submit',
        '#value' => t('Fjern den sidste'),
        '#submit' => array('::removeParticipant'),
        '#ajax' => [
          'callback' => '::addRemoveParticipantsCallback',
          'wrapper' => 'participants-container-wrapper',
        ],
        '#limit_validation_errors' => array()
      ];
    }
    //END PARTICIPANTS CONTAINER //

    //START FORM CONTROLS //
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Indsend oplynsinger'),
    );
    //END FORM CONTROLS //

    $form['#theme'] = 'vih_subscription_short_course_order_form';
    $form_state->setCached(FALSE);
    return $form;
  }

  /**
   * Ajax callback that fill the list of options.
   * @param array $form
   * @param FormStateInterface $form_state
   * @return mixed
   */
  public function ajaxFillOptionsList(array &$form, FormStateInterface $form_state) {
    $selectedOptionGroup = $form_state->getValue('optionGroups');
    $optionGroupOptions = $form_state->get('optionGroupOptions');

    $form['availableOptionsContainer']['optionsContainer']['options']['#options'] = array(-1 => t('- None -'));
    if (is_numeric($selectedOptionGroup) && $selectedOptionGroup != -1) {
      $form['availableOptionsContainer']['optionsContainer']['options']['#options'] += $optionGroupOptions[$selectedOptionGroup];
    }

    return $form['availableOptionsContainer']['optionsContainer'];
  }

  /**
   * Ajax callback that fills the list of suboptions.
   *
   * @param array $form
   * @param FormStateInterface $form_state
   * @return mixed
   */
  public function ajaxFillSuboptionsList(array &$form, FormStateInterface $form_state) {
    $userInput = $form_state->getUserInput();

    $selectedOptionGroup = $form_state->getValue('optionGroups');
    $selectedOption = $userInput['options'];

    $optionGroupSuboptions = $form_state->get('optionGroupSuboptions');
    $form['availableOptionsContainer']['optionsContainer']['suboptionsContainer']['suboptions']['#options'] = $optionGroupSuboptions[$selectedOptionGroup][$selectedOption];
    if (!empty($optionGroupSuboptions[$selectedOptionGroup][$selectedOption])) {
      $form['availableOptionsContainer']['optionsContainer']['suboptionsContainer']['suboptions']['#access'] = TRUE;
    }

    return $form['availableOptionsContainer']['optionsContainer']['suboptionsContainer'];
  }

  /**
   * Callback function that adds the certain option to the list of added options.
   *
   * @param array $form
   * @param FormStateInterface $form_state
   */
  public function addOption(array &$form, FormStateInterface $form_state) {
    $userInput = $form_state->getUserInput();

    if ($form_state->hasAnyErrors()) {
      $response = new AjaxResponse();
      $response->addCommand(new ReplaceCommand('#status_messages', $form['status_messages']));
      return $response;
    }

    $addedOptions = $form_state->get('addedOptions');
    $option = [
      'optionGroup' => $userInput['optionGroups'],
      'option' => $userInput['options'],
      'suboption' => $userInput['suboptions'],
      'amount' => $userInput['amount']
    ];
    $addedOptions[] = $option;
    $form_state->set('addedOptions', $addedOptions);

    $form_state->setRebuild();
  }

  /**
   * Callback function that removed a previously added option.
   *
   * @param array $form
   * @param FormStateInterface $form_state
   */
  public function removeOption(array &$form, FormStateInterface $form_state) {
    $triggeringElement = $form_state->getTriggeringElement();
    $addedOptionDelta = $triggeringElement['#addedOptionDelta'];

    $addedOptions = $form_state->get('addedOptions');
    unset($addedOptions[$addedOptionDelta]);
    $form_state->set('addedOptions', $addedOptions);

    $form_state->setRebuild();
  }

  /**
   * Ajax callback function that fetches the refreshed version of added options, resets available options
   * as well as updated the total course price
   *
   * @param array $form
   * @param FormStateInterface $form_state
   * @return AjaxResponse
   */
  public function ajaxAddRemoveOptionCallback(array &$form, FormStateInterface $form_state) {
    //checking for any errors
    if ($form_state->hasAnyErrors()) {
      $response = new AjaxResponse();
      $response->addCommand(new HtmlCommand('#status_messages', $form['status_messages']));
      //redrawing elements
      $response->addCommand(new ReplaceCommand('#added-options-container-wrapper', $form['addedOptionsContainer']));
      $response->addCommand(new ReplaceCommand('#available-options-container-wrapper', $form['availableOptionsContainer']));
      return $response;
    }

    //diselecting option groups
    $form['availableOptionsContainer']['optionGroups']['#value'] = -1;

    $response = new AjaxResponse();
    //resetting elements
    $response->addCommand(new ReplaceCommand('#added-options-container-wrapper', $form['addedOptionsContainer']));
    $response->addCommand(new ReplaceCommand('#available-options-container-wrapper', $form['availableOptionsContainer']));

    //updating the price
    $response->addCommand(new HtmlCommand('#vih-course-price', number_format($this->calculatePrice($form_state), 2)));

    //resetting the error, if any
    $response->addCommand(new HtmlCommand('#status_messages', $form['status_messages']));

    return $response;
  }


  /**
   * Callback function to add more participants fieldset
   *
   * @param array $form
   * @param FormStateInterface $form_state
   */
  public function addParticipant(array &$form, FormStateInterface $form_state) {
    $participantsCounter = $form_state->get('participantsCounter');
    $participantsCounter = $participantsCounter + 1;
    $form_state->set('participantsCounter', $participantsCounter);
    $form_state->setRebuild();
  }

  /**
   * Ajax callback the returns the participants_container container
   *
   * @param array $form
   * @param FormStateInterface $form_state
   * @return mixed
   */
  public function addRemoveParticipantsCallback(array &$form, FormStateInterface $form_state) {
    return $form['participantsContainer'];
  }

  /**
   * Callback function to remove the last added participants fieldset
   *
   * @param array $form
   * @param FormStateInterface $form_state
   */
  public function removeParticipant(array &$form, FormStateInterface $form_state) {
    $participantsCounter = $form_state->get('participantsCounter');
    if ($participantsCounter > 1) {
      $participantsCounter = $participantsCounter - 1;
      $form_state->set('participantsCounter', $participantsCounter);
    }
    $form_state->setRebuild();
  }


  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $userInput = $form_state->getUserInput();
    $triggeringElement = $form_state->getTriggeringElement();

    if ($triggeringElement['#id'] == 'add-option') {
      if ($userInput['optionGroups'] == -1) {
        $form_state->setErrorByName('optionGroups', $this->t('This is not a .com email address.'));
      }
      if ($userInput['options'] == -1) {
        $form_state->setErrorByName('options', $this->t('This is not a .com email address.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    //collection ordered options
    $orderedOptions = array();
    $addedOptions = $form_state->get('addedOptions');
    if (isset($addedOptions) && !empty($addedOptions)) {
      $optionGroups = $this->course->field_vih_sc_option_groups->referencedEntities();
      foreach ($addedOptions as $addedOption) {
        $optionGroup = $optionGroups[$addedOption['optionGroup']];

        $options = $optionGroup->field_vih_og_options->referencedEntities();
        $option = $options[$addedOption['option']];

        $suboption = null;
        if ($option && isset($addedOption['suboption'])) {
          $suboptions = $option->field_vih_option_suboptions;
          $suboption = $suboptions[$addedOption['suboption']]->value;
        }

        $orderedOption = Paragraph::create([
          'type' => 'vih_ordered_option',
          'field_vih_oo_group_name' => $optionGroup->field_vih_og_title->value,
          'field_vih_oo_option_name' => $option->field_vih_option_title->value,
          'field_vih_oo_price_addition' => ($option->field_vih_option_price_addition->value)*$addedOption['amount'],
          'field_vih_oo_suboption' => $suboption,
          'field_vih_oo_amount' => $addedOption['amount'],
        ]);
        $orderedOptions[] = $orderedOption;
      }
    }

    //collection subscribed persons
    $firstParticipantName = '';
    $subscribedPersons = array();
    foreach($form_state->getValue('participantsContainer') as $participant_fieldset) {
      $participant = $participant_fieldset['participant_fieldset'];
      if ($participant) {
        if (empty($firstParticipantName)) {
          $firstParticipantName = $participant['firstName'] . ' ' . $participant['lastName'];
        }

        $subscribedPerson = Paragraph::create([
          'type' => 'vih_ordered_course_person',
          'field_vih_ocp_first_name' => $participant['firstName'],
          'field_vih_ocp_last_name' => $participant['lastName'],
          'field_vih_ocp_email' => $participant['email'],
        ]);
        $subscribedPerson->save();
        $subscribedPersons[] = $subscribedPerson;
      }
    }

    $orderPrice = $this->calculatePrice($form_state);

    //creating the Course Order
    $this->courseOrder = Node::create(array(
      'type' => 'vih_short_course_order',
      'status' => 0,
      'title' => $this->course->getTitle() . ' - kursus tilmelding - ' . $firstParticipantName . ' - '
        . \Drupal::service('date.formatter')->format(time(), 'short'),
      'field_vih_sco_persons' => $subscribedPersons,
      'field_vih_sco_ordered_options' => $orderedOptions,
      'field_vih_sco_course' => $this->course->id(),
      'field_vih_sco_status' => 'pending',
      'field_vih_sco_price' => $orderPrice
    ));
    $this->courseOrder->save();

    //generating URL needed for quickpay
    $successUrl = Url::fromRoute('vih_subscription.subscription_successful_redirect', [
      'subject' => $this->course->id(),
      'order' => $this->courseOrder->id(),
      'checksum' => VihSubscriptionUtils::generateChecksum($this->course, $this->courseOrder)
    ]);
    $successUrl->setAbsolute();

    $cancelUrl = Url::fromRoute('vih_subscription.subscription_cancelled_redirect');
    $cancelUrl->setAbsolute();

    $client = new BellcomQuickpayClient();
    $paymentLink = $client->getPaymentLink($this->courseOrder, $this->course, $orderPrice, $successUrl->toString(), $cancelUrl->toString());

    //successful
    if ($paymentLink) {
      //following the link
      $response = new TrustedRedirectResponse($paymentLink);
      $form_state->setResponse($response);
    } else {
      //something was wrong
      $form_state->setRedirect('vih_subscription.subscription_error_redirect');
    }
  }

  /**
   * Traverses through the selected options and calculates the total price for the course.
   *
   * @param FormStateInterface $form_state
   * @return mixed
   */
  private function calculatePrice(FormStateInterface $form_state) {
    $base_price = $this->course->field_vih_sc_price->value;

    $addedOptions = $form_state->get('addedOptions');
    $optionGroups = $this->course->field_vih_sc_option_groups->referencedEntities();

    foreach ($addedOptions as $addedOption) {
      $optionGroup = $optionGroups[$addedOption['optionGroup']];

      $options = $optionGroup->field_vih_og_options->referencedEntities();
      $option = $options[$addedOption['option']];

      $base_price += ($option->field_vih_option_price_addition->value) * $addedOption['amount'];
    }

    $this->price = $base_price;
    return $this->price;
  }
}