<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\CustomerRepository;
use App\Repositories\StoreRepository;
use App\Services\Auth\CsrfService;

class CustomerController extends Controller
{
    public function __construct(
        private CustomerRepository $customers,
        private StoreRepository $stores,
        private CsrfService $csrf
    ) {
        parent::__construct();
    }

    public function index()
	{
		$filters = [
			'store_id' => $this->request->input('store_id'),
			'status' => $this->request->input('status'),
			'search' => trim((string) $this->request->input('search')),
		];

		return $this->view('admin.customers.index', [
			'title' => 'Customers',
			'customers' => $this->customers->all($filters),
			'stores' => $this->stores->all(),
			'filters' => $filters,
			'success' => $_SESSION['customers_success'] ?? null,
			'error' => $_SESSION['customers_error'] ?? null,
		], 'admin');
	}
    public function create()
    {
        return $this->view('admin.customers.create', [
            'title' => 'Create Customer',
            'stores' => $this->stores->all(),
            'csrf_token' => $this->csrf->token(),
            'error' => $_SESSION['customers_error'] ?? null,
        ], 'admin');
    }

    public function store()
    {
        $csrfToken = (string) $this->request->input('_csrf_token');

        if (! $this->csrf->validate($csrfToken)) {
            $_SESSION['customers_error'] = 'Security token expired. Please try again.';
            $this->response->redirect('/admin/customers/create');
        }

        $storeId = (int) $this->request->input('store_id');
        $firstName = trim((string) $this->request->input('first_name'));
        $lastName = trim((string) $this->request->input('last_name'));
        $email = trim((string) $this->request->input('email'));
        $phone = trim((string) $this->request->input('phone'));
        $addressLine1 = trim((string) $this->request->input('address_line_1'));
        $addressLine2 = trim((string) $this->request->input('address_line_2'));
        $city = trim((string) $this->request->input('city'));
        $state = trim((string) $this->request->input('state'));
        $postalCode = trim((string) $this->request->input('postal_code'));
        $country = trim((string) $this->request->input('country'));
        $status = trim((string) $this->request->input('status'));

        if ($storeId <= 0 || $firstName === '' || $lastName === '') {
            $_SESSION['customers_error'] = 'Store, first name, and last name are required.';
            $this->response->redirect('/admin/customers/create');
        }

        if (! $this->stores->find($storeId)) {
            $_SESSION['customers_error'] = 'Selected store does not exist.';
            $this->response->redirect('/admin/customers/create');
        }

        if ($email !== '' && $this->customers->findByStoreAndEmail($storeId, $email)) {
            $_SESSION['customers_error'] = 'A customer with that email already exists for this store.';
            $this->response->redirect('/admin/customers/create');
        }

        $this->customers->create([
            'store_id' => $storeId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'address_line_1' => $addressLine1,
            'address_line_2' => $addressLine2,
            'city' => $city,
            'state' => $state,
            'postal_code' => $postalCode,
            'country' => $country,
            'status' => $status,
        ]);

        $this->csrf->regenerate();

        $_SESSION['customers_success'] = 'Customer created successfully.';

        $this->response->redirect('/admin/customers');
    }

    public function edit(Request $request)
    {
        $id = (int) $request->route('id');

        $customer = $this->customers->find($id);

        if (! $customer) {
            http_response_code(404);
            return '404 - Customer not found';
        }

        return $this->view('admin.customers.edit', [
            'title' => 'Edit Customer',
            'customer' => $customer,
            'stores' => $this->stores->all(),
            'csrf_token' => $this->csrf->token(),
            'error' => $_SESSION['customers_error'] ?? null,
        ], 'admin');
    }

    public function update(Request $request)
    {
        $id = (int) $request->route('id');

        $csrfToken = (string) $this->request->input('_csrf_token');

        if (! $this->csrf->validate($csrfToken)) {
            $_SESSION['customers_error'] = 'Security token expired. Please try again.';
            $this->response->redirect('/admin/customers/' . $id . '/edit');
        }

        $customer = $this->customers->find($id);

        if (! $customer) {
            http_response_code(404);
            return '404 - Customer not found';
        }

        $storeId = (int) $this->request->input('store_id');
        $firstName = trim((string) $this->request->input('first_name'));
        $lastName = trim((string) $this->request->input('last_name'));
        $email = trim((string) $this->request->input('email'));
        $phone = trim((string) $this->request->input('phone'));
        $addressLine1 = trim((string) $this->request->input('address_line_1'));
        $addressLine2 = trim((string) $this->request->input('address_line_2'));
        $city = trim((string) $this->request->input('city'));
        $state = trim((string) $this->request->input('state'));
        $postalCode = trim((string) $this->request->input('postal_code'));
        $country = trim((string) $this->request->input('country'));
        $status = trim((string) $this->request->input('status'));

        if ($storeId <= 0 || $firstName === '' || $lastName === '') {
            $_SESSION['customers_error'] = 'Store, first name, and last name are required.';
            $this->response->redirect('/admin/customers/' . $id . '/edit');
        }

        if (! $this->stores->find($storeId)) {
            $_SESSION['customers_error'] = 'Selected store does not exist.';
            $this->response->redirect('/admin/customers/' . $id . '/edit');
        }

        $existingCustomer = $email !== ''
            ? $this->customers->findByStoreAndEmail($storeId, $email)
            : null;

        if ($existingCustomer && (int) $existingCustomer['id'] !== $id) {
            $_SESSION['customers_error'] = 'A customer with that email already exists for this store.';
            $this->response->redirect('/admin/customers/' . $id . '/edit');
        }

        $this->customers->update($id, [
            'store_id' => $storeId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'address_line_1' => $addressLine1,
            'address_line_2' => $addressLine2,
            'city' => $city,
            'state' => $state,
            'postal_code' => $postalCode,
            'country' => $country,
            'status' => $status,
        ]);

        $this->csrf->regenerate();

        $_SESSION['customers_success'] = 'Customer updated successfully.';

        $this->response->redirect('/admin/customers');
    }

	public function show(Request $request)
	{
		$id = (int) $request->route('id');

		$customer = $this->customers->find($id);

		if (! $customer) {
			http_response_code(404);
			return '404 - Customer not found';
		}

		return $this->view('admin.customers.show', [
			'title' => $customer['first_name'] . ' ' . $customer['last_name'],
			'customer' => $customer,
			'stats' => $this->customers->stats($id),
			'orders' => $this->customers->ordersForCustomer($id),
		], 'admin');
	}

}