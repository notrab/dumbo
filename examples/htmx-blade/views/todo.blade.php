<div id="todo-list">
    <form hx-post="/todos" hx-target="#todo-list" hx-swap="outerHTML" class="mb-4">
        <div class="flex">
            <input type="text" name="task" placeholder="Add a new task" required class="flex-grow mr-2 p-2 border rounded">
            <button type="submit" class="bg-teal-500 hover:bg-teal-700 text-white font-bold py-2 px-4 rounded">Add</button>
        </div>
    </form>
    <ul class="space-y-2">
        @foreach ($todos as $todo)
            <li class="flex items-center justify-between bg-gray-100 p-2 rounded">
                <span class="{{ $todo['completed'] ? 'line-through text-gray-500' : 'text-gray-800' }}"
                      hx-put="/todos/{{ $todo['id'] }}"
                      hx-target="#todo-list"
                      hx-swap="outerHTML">
                    {{ $todo['task'] }}
                </span>
                <button hx-delete="/todos/{{ $todo['id'] }}"
                        hx-target="#todo-list"
                        hx-swap="outerHTML"
                        class="bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-2 rounded text-sm">
                    Delete
                </button>
            </li>
        @endforeach
    </ul>
</div>
