using UnityEngine;

public class PlayerMovement : MonoBehaviour
{
    public float speed = 5f;

    void Update()
    {
        float horizontal = Input.GetAxis("Horizontal");   // A / D  or ← →
        float vertical   = Input.GetAxis("Vertical");     // W / S  or ↑ ↓

        Vector3 movement = new Vector3(horizontal, 0f, vertical);

        if (movement.magnitude > 1f)
            movement.Normalize();

        transform.Translate(movement * speed * Time.deltaTime);
    }
}