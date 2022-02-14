<?php

namespace App\Handler\ChatBot;

use App\Entity\ChatBot\Answer;
use App\Entity\ChatBot\Question;
use App\Http\Exception\AppException;
use App\Http\Request\Admin\ChatBot\CreateQuestionRequest;
use App\Http\Request\Admin\ChatBot\UpdateQuestionRequest;
use App\Repository\ChatBot\QuestionRepository;
use App\Service\Translate\TranslationHelper;

/**
 * Class AdminQuestionHandler
 * @package App\Handler\ChatBot
 */
class AdminQuestionHandler
{
    private $questionRepository;
    private $translationHelper;

    public function __construct(
        QuestionRepository $questionRepository,
        TranslationHelper $translationHelper
    ) {
        $this->questionRepository = $questionRepository;
        $this->translationHelper = $translationHelper;
    }

    /**
     * @param CreateQuestionRequest $request
     * @return Question
     * @throws \Exception
     */
    public function create(CreateQuestionRequest $request): Question
    {
        if (!$category = $this->createQuestionCategory($request->getCategories())) {
            throw new AppException('Runtime exception', 406);
        }

        $question = new Question($category, Question::TYPE_QUESTION);
        $this->questionRepository->save($question);

        $this->translationHelper->setEntityTranslations($question, $request->getQuestionTranslations());

        if (!$answer = $question->getAnswer()) {
            $answer = new Answer($question);
            $this->questionRepository->save($answer);
        }
        $this->translationHelper->setEntityTranslations($answer, $request->getAnswerTranslations());

        return $question;
    }

    /**
     * @param UpdateQuestionRequest $request
     * @param Question $question
     * @throws \Exception
     */
    public function update(UpdateQuestionRequest $request, Question $question)
    {
        if (!$category = $this->createQuestionCategory($request->getCategories())) {
            throw new AppException('Runtime exception', 406);
        }

        $this->translationHelper->setEntityTranslations($question, $request->getQuestionTranslations());
        $question->setParent($category);
        $question->setUpdatedAt();

        $answer = $question->getAnswer();
        if (!$answer) {
            $answer = new Answer($question);
            $this->questionRepository->save($answer);
        } else {
            $answer->setUpdatedAt();
        }
        $this->translationHelper->setEntityTranslations($answer, $request->getAnswerTranslations());
    }

    /**
     * @param Question $question
     */
    public function delete(Question $question)
    {
        if ($question->isCategory() && count($question->getChildren()) > 0) {
            throw new AppException('chat_bot.exception.question.delete_category_is_not_empty', 406);
        }

        $this->questionRepository->delete($question);
    }

    /**
     * @param array $categories
     * @return Question|null
     * @throws \Exception
     */
    private function createQuestionCategory(array $categories): ?Question
    {
        usort($categories, function ($a, $b) {
            $levelA = $a['level'] ?? 0;
            $levelB = $b['level'] ?? 0;

            return $levelA - $levelB;
        });

        $categoryIsNew = false;
        $parent = null;
        $category = null;
        foreach ($categories as $categoryData) {
            if (!array_key_exists('level', $categoryData)) {
                continue;
            }

            $category = null;
            if (array_key_exists('id', $categoryData)) {
                $category = $this->questionRepository->find($categoryData['id']);
                if ($category && !$category->isCategory()) {
                    throw new AppException('Runtime exception', 406);
                }
                if ($categoryIsNew) {
                    throw new AppException('Runtime exception', 406);
                }
            }

            if (!$category) {
                $categoryIsNew = true;
                $category = new Question($parent, Question::TYPE_CATEGORY);
                $this->questionRepository->save($category);
            } else {
                $category->setUpdatedAt();
            }

            $this->translationHelper->setEntityTranslations($category, $categoryData['translations'] ?? []);

            $parent = $category;
        }

        return $category;
    }
}