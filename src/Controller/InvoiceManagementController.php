<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\Product;
use App\Entity\Invoice;
use App\Entity\InvoiceProduct;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/invoice-management')]
class InvoiceManagementController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    // --- Helper methods ---
    private function customerToArray(Customer $c): array {
        return [
            'id' => $c->getId(),
            'name' => $c->getName(),
            'email' => $c->getEmail(),
            'phone' => $c->getPhone(),
            'address' => $c->getAddress(),
        ];
    }
    private function productToArray(Product $p): array {
        return [
            'id' => $p->getId(),
            'name' => $p->getName(),
            'description' => $p->getDescription(),
            'price' => $p->getPrice(),
        ];
    }
    private function invoiceProductToArray(InvoiceProduct $ip): array {
        return [
            'id' => $ip->getId(),
            'product' => $this->productToArray($ip->getProduct()),
            'quantity' => $ip->getQuantity(),
            'price' => $ip->getPrice(),
        ];
    }
    private function invoiceToArray(Invoice $i): array {
        return [
            'id' => $i->getId(),
            'invoice_number' => $i->getInvoiceNumber(),
            'date' => $i->getDate()->format('c'),
            'customer' => $this->customerToArray($i->getCustomer()),
            'total' => $i->getTotal(),
            'items' => array_map(fn($ip) => $this->invoiceProductToArray($ip), $i->getInvoiceProducts()->toArray()),
        ];
    }

    // --- CUSTOMER CRUD ---
    #[Route('/customers', methods: ['GET'])]
    public function listCustomers(): JsonResponse {
        $customers = $this->em->getRepository(Customer::class)->findAll();
        $data = array_map(fn($c) => $this->customerToArray($c), $customers);
        return $this->json($data);
    }

    #[Route('/customers/{id}', methods: ['GET'])]
    public function getCustomer(int $id): JsonResponse {
        $customer = $this->em->getRepository(Customer::class)->find($id);
        if (!$customer) {
            return $this->json(['error' => 'Customer not found'], 404);
        }
        return $this->json($this->customerToArray($customer));
    }

    #[Route('/customers', methods: ['POST'])]
    public function createCustomer(Request $request): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $customer = new Customer();
        $customer->setName($data['name'] ?? '');
        $customer->setEmail($data['email'] ?? null);
        $customer->setPhone($data['phone'] ?? null);
        $customer->setAddress($data['address'] ?? null);
        $this->em->persist($customer);
        $this->em->flush();
        return $this->json($this->customerToArray($customer), 201);
    }

    #[Route('/customers/{id}', methods: ['PUT'])]
    public function updateCustomer(int $id, Request $request): JsonResponse {
        $customer = $this->em->getRepository(Customer::class)->find($id);
        if (!$customer) {
            return $this->json(['error' => 'Customer not found'], 404);
        }
        $data = json_decode($request->getContent(), true);
        $customer->setName($data['name'] ?? $customer->getName());
        $customer->setEmail($data['email'] ?? $customer->getEmail());
        $customer->setPhone($data['phone'] ?? $customer->getPhone());
        $customer->setAddress($data['address'] ?? $customer->getAddress());
        $this->em->flush();
        return $this->json($this->customerToArray($customer));
    }

    #[Route('/customers/{id}', methods: ['DELETE'])]
    public function deleteCustomer(int $id): JsonResponse {
        $customer = $this->em->getRepository(Customer::class)->find($id);
        if (!$customer) {
            return $this->json(['error' => 'Customer not found'], 404);
        }
        $this->em->remove($customer);
        $this->em->flush();
        return $this->json(['success' => true]);
    }

    // --- PRODUCT CRUD ---
    #[Route('/products', methods: ['GET'])]
    public function listProducts(): JsonResponse {
        $products = $this->em->getRepository(Product::class)->findAll();
        $data = array_map(fn($p) => $this->productToArray($p), $products);
        return $this->json($data);
    }

    #[Route('/products/{id}', methods: ['GET'])]
    public function getProduct(int $id): JsonResponse {
        $product = $this->em->getRepository(Product::class)->find($id);
        if (!$product) {
            return $this->json(['error' => 'Product not found'], 404);
        }
        return $this->json($this->productToArray($product));
    }

    #[Route('/products', methods: ['POST'])]
    public function createProduct(Request $request): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $product = new Product();
        $product->setName($data['name'] ?? '');
        $product->setDescription($data['description'] ?? null);
        $product->setPrice($data['price'] ?? 0.0);
        $this->em->persist($product);
        $this->em->flush();
        return $this->json($this->productToArray($product), 201);
    }

    #[Route('/products/{id}', methods: ['PUT'])]
    public function updateProduct(int $id, Request $request): JsonResponse {
        $product = $this->em->getRepository(Product::class)->find($id);
        if (!$product) {
            return $this->json(['error' => 'Product not found'], 404);
        }
        $data = json_decode($request->getContent(), true);
        $product->setName($data['name'] ?? $product->getName());
        $product->setDescription($data['description'] ?? $product->getDescription());
        $product->setPrice($data['price'] ?? $product->getPrice());
        $this->em->flush();
        return $this->json($this->productToArray($product));
    }

    #[Route('/products/{id}', methods: ['DELETE'])]
    public function deleteProduct(int $id): JsonResponse {
        $product = $this->em->getRepository(Product::class)->find($id);
        if (!$product) {
            return $this->json(['error' => 'Product not found'], 404);
        }
        $this->em->remove($product);
        $this->em->flush();
        return $this->json(['success' => true]);
    }

    // --- INVOICE CRUD ---
    #[Route('/invoices', methods: ['GET'])]
    public function listInvoices(): JsonResponse {
        $invoices = $this->em->getRepository(Invoice::class)->findAll();
        $data = array_map(fn($i) => $this->invoiceToArray($i), $invoices);
        return $this->json($data);
    }

    #[Route('/invoices/{id}', methods: ['GET'])]
    public function getInvoice(int $id): JsonResponse {
        $invoice = $this->em->getRepository(Invoice::class)->find($id);
        if (!$invoice) {
            return $this->json(['error' => 'Invoice not found'], 404);
        }
        return $this->json($this->invoiceToArray($invoice));
    }

    #[Route('/invoices', methods: ['POST'])]
    public function createInvoice(Request $request): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $customer = $this->em->getRepository(Customer::class)->find($data['customer_id'] ?? 0);
        if (!$customer) {
            return $this->json(['error' => 'Customer not found'], 404);
        }
        $invoice = new Invoice();
        $invoice->setInvoiceNumber($data['invoice_number'] ?? '');
        $invoice->setDate(new \DateTime($data['date'] ?? 'now'));
        $invoice->setCustomer($customer);
        $invoice->setTotal($data['total'] ?? 0.0);
        // Handle invoice items
        foreach ($data['items'] ?? [] as $item) {
            $product = $this->em->getRepository(Product::class)->find($item['product_id'] ?? 0);
            if ($product) {
                $invoiceProduct = new InvoiceProduct();
                $invoiceProduct->setProduct($product);
                $invoiceProduct->setQuantity($item['quantity'] ?? 1);
                $invoiceProduct->setPrice($item['price'] ?? $product->getPrice());
                $invoice->addInvoiceProduct($invoiceProduct);
            }
        }
        $this->em->persist($invoice);
        $this->em->flush();
        return $this->json($this->invoiceToArray($invoice), 201);
    }

    #[Route('/invoices/{id}', methods: ['PUT'])]
    public function updateInvoice(int $id, Request $request): JsonResponse {
        $invoice = $this->em->getRepository(Invoice::class)->find($id);
        if (!$invoice) {
            return $this->json(['error' => 'Invoice not found'], 404);
        }
        $data = json_decode($request->getContent(), true);
        if (isset($data['invoice_number'])) {
            $invoice->setInvoiceNumber($data['invoice_number']);
        }
        if (isset($data['date'])) {
            $invoice->setDate(new \DateTime($data['date']));
        }
        if (isset($data['customer_id'])) {
            $customer = $this->em->getRepository(Customer::class)->find($data['customer_id']);
            if ($customer) {
                $invoice->setCustomer($customer);
            }
        }
        if (isset($data['total'])) {
            $invoice->setTotal($data['total']);
        }
        // Update invoice items (replace all)
        if (isset($data['items'])) {
            foreach ($invoice->getInvoiceProducts() as $ip) {
                $this->em->remove($ip);
            }
            foreach ($data['items'] as $item) {
                $product = $this->em->getRepository(Product::class)->find($item['product_id'] ?? 0);
                if ($product) {
                    $invoiceProduct = new InvoiceProduct();
                    $invoiceProduct->setProduct($product);
                    $invoiceProduct->setQuantity($item['quantity'] ?? 1);
                    $invoiceProduct->setPrice($item['price'] ?? $product->getPrice());
                    $invoice->addInvoiceProduct($invoiceProduct);
                }
            }
        }
        $this->em->flush();
        return $this->json($this->invoiceToArray($invoice));
    }

    #[Route('/invoices/{id}', methods: ['DELETE'])]
    public function deleteInvoice(int $id): JsonResponse {
        $invoice = $this->em->getRepository(Invoice::class)->find($id);
        if (!$invoice) {
            return $this->json(['error' => 'Invoice not found'], 404);
        }
        foreach ($invoice->getInvoiceProducts() as $ip) {
            $this->em->remove($ip);
        }
        $this->em->remove($invoice);
        $this->em->flush();
        return $this->json(['success' => true]);
    }
} 