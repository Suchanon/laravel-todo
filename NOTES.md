# สรุป: Props กับ Attributes ใน Blade Component

## Props — ตัวแปรที่ component รับเข้ามา
- คือ "ตัวแปร" ที่เราประกาศไว้ว่า component รับค่าอะไรได้บ้าง
- ประกาศด้วย `@props([...])` ที่หัวไฟล์
- กำหนดค่า default ได้ เช่น `'type' => 'primary'`
- เอาไปใช้ในตรรกะ / แสดงผลข้างใน component

```blade
@props(['type' => 'primary', 'message'])

<button>{{ $message }}</button>
```

เรียกใช้:
```blade
<x-button type="danger" message="ลบ" />
```

## Attributes — แอตทริบิวต์ HTML ที่เหลือ
- คือแอตทริบิวต์ HTML อื่นๆ ที่ "ไม่ได้ประกาศเป็น props" เช่น `class`, `id`, `style`, `disabled`
- มากับตัวแปร `$attributes` อัตโนมัติ (ไม่ต้องประกาศ)
- เอาไปแปะกลับที่ HTML tag

```blade
@props(['message'])

<button {{ $attributes }}>{{ $message }}</button>
```

เรียกใช้:
```blade
<x-button message="บันทึก" class="btn-lg" id="save" disabled />
```

ผลลัพธ์ — `class`, `id`, `disabled` ไหลเข้า `$attributes` แล้วแปะที่ปุ่ม:
```html
<button class="btn-lg" id="save" disabled>บันทึก</button>
```

### merge — รวม class ของเรากับที่ส่งเข้ามา
```blade
<button {{ $attributes->merge(['class' => 'btn']) }}>{{ $message }}</button>
```
→ ได้ `class="btn btn-lg"`

## ตารางเปรียบเทียบ

| | Props | Attributes |
|---|---|---|
| คืออะไร | ตัวแปรที่ประกาศรับเข้ามา | แอตทริบิวต์ HTML ที่เหลือ (ไม่ได้ประกาศ) |
| ประกาศยังไง | `@props([...])` | ไม่ต้องประกาศ มากับ `$attributes` อัตโนมัติ |
| เอาไปใช้ทำอะไร | ใช้ในตรรกะ / แสดงค่า | แปะกลับไปที่ HTML tag |
| ตัวอย่าง | `$message`, `$type` | `class`, `id`, `style` |

## หลักจำง่ายๆ
อะไรที่ประกาศใน `@props` = **props**, ที่เหลือทั้งหมด = **attributes**

---

# สรุป: Blade Directives

## คืออะไร
- คำสั่งพิเศษที่ขึ้นต้นด้วย `@` ใช้เขียน logic (เงื่อนไข, ลูป, เช็คค่า) ในไฟล์ Blade
- เป็น "ทางลัด" ของ PHP — ตอน compile `@if` จะกลายเป็น `<?php if(...): ?>`
- ใช้กับตัวแปรอะไรก็ได้ ไม่จำเป็นต้องเป็น props
- หลักจำ: เห็น `@` นำหน้า = Blade Directive (ส่วน `{{ }}` ใช้แค่แสดงค่า ไม่ใช่ directive)

## ประเภทที่ใช้บ่อย

### 1. เงื่อนไข (Conditionals)
```blade
@if ($type === 'admin')
    <p>คุณคือแอดมิน</p>
@elseif ($type === 'user')
    <p>คุณคือผู้ใช้ทั่วไป</p>
@else
    <p>ไม่ทราบสิทธิ์</p>
@endif
```

### 2. วนลูป (Loops)
```blade
@foreach ($users as $user)
    <li>{{ $user }}</li>
@endforeach
```

### 3. เช็คว่ามีค่าหรือไม่
```blade
@isset($message)
    <p>{{ $message }}</p>
@endisset

@empty($users)
    <p>ไม่มีข้อมูล</p>
@endempty
```

## ตัวอย่างใช้คู่กับ props
เปลี่ยนปุ่มตามค่า prop `$type`:
```blade
@props(['type' => 'primary'])

@if ($type === 'danger')
    <button class="bg-red">ลบ</button>
@else
    <button class="bg-blue">บันทึก</button>
@endif
```

---

# สรุป: Laravel Fundamentals (มุมมองคนมาจาก Nest.js)

ทั้งคู่เป็น MVC framework แบบ full-stack + opinionated แนวคิดคล้ายกันมาก
ต่างที่ Laravel = PHP และ "รวมทุกอย่างมาให้ในกล่อง" (batteries included) มากกว่า

## ตารางเทียบหัวข้อในคอร์ส

| หัวข้อ | Nest.js เทียบเท่า | สรุป |
|---|---|---|
| Routing 101 | `@Controller` + `@Get/@Post` | Laravel แยก route ไว้ใน `routes/web.php` ต่างหาก ไม่แปะบน method |
| Layout Files | (Nest ไม่มีตรงๆ) | template หลักที่ทุกหน้าใช้ร่วม (`<x-layout>` + `{{ $slot }}`) |
| Pass Data to Views | return DTO → frontend | controller ส่ง data เข้า Blade view เพื่อ render HTML ฝั่ง server |
| Blade Directives | logic ใน template engine | `@if`, `@foreach` เขียน logic ในไฟล์ view |
| Forms | รับ form ฝั่ง client | ฟอร์ม HTML + CSRF token |
| Databases/Migrations/Eloquent | TypeORM/Prisma + Entity | Eloquent = ORM, Migration = schema versioning |
| HTTP Requests & REST | RESTful routing | GET/POST/PUT/DELETE ตามหลัก REST |
| Controllers | `@Controller` class | คลาสรวม method จัดการ request |
| Request Validation | `class-validator` + DTO | `$request->validate([...])` |
| Form Request Classes | DTO class | แยก validation ออกเป็นคลาสต่างหาก |

## Request Lifecycle เทียบกัน

**Nest.js:**
```
Request → Controller (@Post) → DTO validation → Service → ORM → Response (JSON)
```

**Laravel (web/MVC):**
```
Request → routes/web.php → Controller → Form Request (validate) → Eloquent → View (HTML)
```
โครงเหมือนกัน ต่างปลายทาง: Nest มักคืน JSON (API), Laravel ในคอร์สนี้คืน HTML (Blade)
แต่ Laravel ทำ API คืน JSON ได้เหมือนกัน

## จุดที่ต้องปรับ mindset
1. **Route แยกไฟล์** — ลงทะเบียนใน `routes/web.php` ไม่ใช้ decorator
   ```php
   Route::get('/contact', [ContactController::class, 'index']);
   ```
2. **ไม่มี Service layer บังคับ** — มักใส่ logic ใน controller หรือ Model เลย (แยกเองได้)
3. **Eloquent = Active Record** — Model คุยกับ DB ได้เอง `User::find(1)`, `$user->save()`
   (ต่างจาก Prisma/TypeORM ที่เป็น Data Mapper)
4. **Blade = server-side rendering** — render HTML จากฝั่ง server เลย
5. **Form Request Classes ≈ DTO** — แยก validation rule เป็นคลาส
   ```php
   class StorePostRequest extends FormRequest {
       public function rules() {
           return ['title' => 'required|max:255'];
       }
   }
   // ใช้: public function store(StorePostRequest $request)
   ```

## Request object เทียบ Nest
`$request` = `@Req()` ของ Nest แต่รวม `@Body` + `@Query` + `@Param` + validation ไว้ในตัวเดียว

| Laravel | Nest.js | ทำอะไร |
|---|---|---|
| `$request->input('name')` | `@Body('name')` | ดึงจาก body |
| `$request->query('q')` | `@Query('q')` | ดึงจาก query string |
| `$request->all()` | `req.body` ทั้งก้อน | ดึงทุก input |
| `$request->only([...])` | — | ดึงเฉพาะ field ที่เลือก |
| `$request->has('name')` | — | เช็คว่ามี field ไหม |
| `$request->validate([...])` | DTO + class-validator | ตรวจ + validate |

## หลักจำรวบยอด
Laravel = "Nest.js + template engine + ORM + ทุกอย่าง รวมในกล่องเดียว"
โดยปลายทางคอร์สนี้ render HTML แทน JSON
