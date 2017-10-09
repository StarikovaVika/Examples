<?php

namespace AppBundle\Controller;

use AppBundle\Model\Base\ItemCart;
use AppBundle\Model\Base\ProductQuery;
use AppBundle\Model\Cart;
use AppBundle\Model\CartQuery;
use AppBundle\Model\Comment;
use AppBundle\Model\CommentQuery;
use AppBundle\Model\ItemCartQuery;
use AppBundle\Model\MyQuestionQuery;
use AppBundle\Model\InformQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="index")
     */
    public function indexAction(Request $request)
    {
        $page = $request->get('page') ?: 1;

        $pager = MyQuestionQuery::create()->orderByCreatedAt(Criteria::DESC)->paginate($page, $max_per_page);

        $lastPage = $pager->getLastPage();
        $questions = $pager->getResults();

        if($request->isXmlHttpRequest()) {
            return new JsonResponse(
                [
                    'html' => $this->renderView('/default/table.html.twig', [
                        'questions' => $questions,
                        'pager' => $pager,
                        'number_page' => $page,
                        'last_page' => $lastPage])
                ]
            );
        }

        return $this->render('/default/index.html.twig', [
            'questions'=> $questions,
            'pager' => $pager,
            'number_page' => $page,
            'last_page' => $lastPage
        ]);
    }

	 /**
     * @Route("/ask/send/", name="ask_send")
     */
    public function sendAction(Request $request)
    {
        $name = $request->get('name');
        $surname = $request->get('surname');
        $email = $request->get('email');

        if($request->isXmlHttpRequest()) {

            $question = new MyQuestion();
            $question->setName($name)
                ->setSurname($surname)
                ->setEmail($email)
                ->save();

            return new JsonResponse();
        }
        return new JsonResponse("send error", 502);
    }

	
    /**
     * @Route("/news/", name="news")
     */
    public function newsAction(Request $request)
    {
        $max_per_page = 2;
        $page = $request->get('page') ?: 1;

        $pager = InformQuery::create()->filterByPublishedAt(['max' => time()])->filterByVisible(true)->orderByPublishedAt(Criteria::DESC)->paginate($page, $max_per_page);


        $articles = $pager->getResults();

        if($request->isXmlHttpRequest()) {
            $html = '';
            foreach ($articles as $item) {
                $html .= '<div class="articles__list-item">';
                $html .= $this->renderView('/inform/article-item.html.twig', [
                    'item' => $item]);
                $html .= '</div>';
            }
            return new JsonResponse(
                [
                    'html' => $html,
                    'is_last_page' => $pager->isLastPage(),
                    'articles' => $articles,
                    'pager' => $pager,
                    'number_page' => $page
                ]
            );
        }

        return $this->render('/inform/index.html.twig', [
            'articles'=> $articles,
            'pager' => $pager,
            'number_page' => $page,
            'is_last_page' => $pager->isLastPage()
        ]);
    }

    /**
     * @Route("/news/{slug}/", name="show_news_item")
     */
    public function showItemAction(Request $request, $slug)
    {
        $news_item = InformQuery::create()->filterByVisible(true)->filterByPublishedAt(['max' => time()])->findByUrl($slug);
        $max_per_page = 2;
        $in_comment = $request->get('comment');
        if($news_item->isEmpty()) {
            return $this->redirect('/news/');
        }
        else {
            $other_news = InformQuery::create()->filterByVisible(true)->filterByPublishedAt(['max' => time()])
                ->filterByUrl($slug, Criteria::NOT_EQUAL)->limit(3)->find();
            $pager = CommentQuery::create()->filterByInform($news_item)->filterByCommentId(NULL)->orderByCreatedAt(Criteria::DESC)->paginate(1, $max_per_page);
            $is_last_page = $pager->isLastPage();
            return $this->render('/inform/view.html.twig', [
                'news_item' => $news_item[0], 'in_comment' => $in_comment,
                'other_news' => $other_news, "pager" => $pager, "is_last_page" => $pager->isLastPage()
            ]);
        }
    }

    /**
     * @Route("/comment/", name="comment")
     */
    public function commentAction(Request $request)
    {
        if($request->isXmlHttpRequest()) {
            $max_per_page = 2;
            $page = $request->get('page') ?: 1;
            $news_id = $request->get('holder_id');
            $comment_id = $request->get('comment_id');

            if($request->getMethod() == 'POST') {
                $name = $request->get('name');
                $email = $request->get('email');
                $content = $request->get('content');

                $comment = new Comment();
                $comment->setName($name)
                    ->setEmail($email)
                    ->setContent($content)
                    ->setInformId($news_id)
                    ->setCommentId($comment_id)
                    ->save();
            }
            $is_last_page = true;
            if($comment_id)
                $pager = CommentQuery::create()->filterByCommentId($comment_id)->orderByCreatedAt(Criteria::DESC);
            else {
                $pager = CommentQuery::create()->filterByInformId($news_id)->filterByCommentId(NULL)->orderByCreatedAt(Criteria::DESC)->paginate($page, $max_per_page);
                $is_last_page = $pager->isLastPage();
            }
            $html = '';
            foreach ($pager as $item) {
                $html .= '<div js-category-list-item class="feedback-list__item feedback-list__item_dots_centered">';
                $html .= $this->renderView('feedback-card.html.twig', [
                    'news_comment' => $item]);
                $html .= '</div>';
            }
            return new JsonResponse(
                [
                    'html' => $html,
                    'is_last_page' => $is_last_page
                ]
            );
        }
        return $this->redirect('/news/');
    }


    /**
     * @Route("/products/", name="products")
     */
    public function productsAction(Request $request)
    {
        $user = $request->getSession()->getId();
        $cart = CartQuery::create()->findOneByUser($user);
        $added_products = $cart->getItemCarts()->getColumnValues('productId');
        if(!$cart) {
            $cart = new Cart();
            $cart
                ->setUser($user)
                ->save();
        }
        $max_per_page = 5;
        $pager = ProductQuery::create()->paginate($cart->getId(), $max_per_page);
        return $this->render('/product/index.html.twig', [
            'pager' => $pager, 'products' => $pager->getResults(),
            'cart' => $cart, 'added_products' => $added_products
        ]);
    }

    /**
     * @Route("/cart/", name="cart")
     */
    public function cartAction(Request $request)
    {
        $user = $request->getSession()->getId();
        $cart = CartQuery::create()->findOneByUser($user);
        if(!$cart) {
            $cart = new Cart();
            $cart
                ->setUser($user)
                ->save();
        }
        $item_cart = $cart->getItemCartsJoinProduct();
        return $this->render('/product/cart.html.twig', [
            'item_cart' => $item_cart, 'cart' => $cart
        ]);
    }

    /**
     * @Route("/cart_product/", name="cart_product")
     */
    public function cartProductAction(Request $request)
    {
        $service = $this->container->get('app.product service');

        $item_id = $request->get('item_id');
        $count = $request->get('count');
        $product_id = $request->get('product_id');

        $user = $request->getSession()->getId();
        $cart = CartQuery::create()->findOneByUser($user);

        if($request->isXmlHttpRequest()) {

            $method = $request->getMethod();
            if ($method == 'PUT') {
                $item = ItemCartQuery::create()->findPk($item_id);
                $service->update($item_id, $count);
                return new JsonResponse(['total_cost' => $cart->getTotalCost(), 'cost'=> $item->getCost(), 'total_count'=> $cart->getCountProducts()]);

            } else if ($method == 'DELETE') {
                if ($item_id) {
                    $service->delete($item_id);
                    return new JsonResponse(['total_cost' => $cart->getTotalCost(), 'total_count'=> $cart->getCountProducts()]);

                } else {
                    $service->clear($cart->getId());
                    return new JsonResponse(['total_cost' => $cart->getTotalCost(), 'total_count'=> $cart->getCountProducts()]);
                }
            } else if ($method == 'POST') {
                $item = $service->add($cart->getId(), $product_id);
                return new JsonResponse(['total_cost' => $cart->getTotalCost(), 'total_count'=> $cart->getCountProducts()]);

            }
        }
        return $this->redirect('/products/');
    }
}
